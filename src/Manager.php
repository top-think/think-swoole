<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use Closure;
use Exception;
use Swoole\Process;
use Swoole\Server;
use think\App;
use think\console\Output;
use think\exception\Handle;
use think\helper\Str;
use think\swoole\App as SwooleApp;
use think\swoole\concerns\InteractsWithHttp;
use think\swoole\concerns\InteractsWithRpc;
use think\swoole\concerns\InteractsWithServer;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\concerns\InteractsWithWebsocket;
use think\swoole\pool\Cache;
use think\swoole\pool\Db;
use Throwable;

/**
 * Class Manager
 */
class Manager
{
    use InteractsWithServer, InteractsWithSwooleTable, InteractsWithHttp, InteractsWithWebsocket, InteractsWithRpc;

    /**
     * @var App
     */
    protected $container;

    /**
     * @var SwooleApp
     */
    protected $app;

    /** @var PidManager */
    protected $pidManager;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'workerError',
        'packet',
        'task',
        'finish',
        'pipeMessage',
        'managerStart',
        'managerStop',
        'request',
    ];

    /**
     * Manager constructor.
     * @param App        $container
     * @param PidManager $pidManager
     */
    public function __construct(App $container, PidManager $pidManager)
    {
        $this->container  = $container;
        $this->pidManager = $pidManager;
    }

    /**
     * 启动服务
     */
    public function run(): void
    {
        $this->initialize();
        if ($this->getConfig('hot_update.enable', false)) {
            //热更新
            $this->addHotUpdateProcess();
        }

        $this->getServer()->start();
    }

    /**
     * 停止服务
     */
    public function stop(): void
    {
        $this->getServer()->shutdown();
    }

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->createTables();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
        $this->prepareRpc();
    }

    /**
     * 获取配置
     * @param string $name
     * @param null   $default
     * @return mixed
     */
    public function getConfig(string $name, $default = null)
    {
        return $this->container->config->get("swoole.{$name}", $default);
    }

    /**
     * 触发事件
     * @param $event
     * @param $params
     */
    protected function triggerEvent(string $event, $params = null): void
    {
        $this->container->event->trigger("swoole.{$event}", $params);
    }

    /**
     * 监听事件
     * @param string $event
     * @param        $listener
     * @param bool   $first
     */
    protected function onEvent(string $event, $listener, bool $first = false): void
    {
        $this->container->event->listen("swoole.{$event}", $listener, $first);
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->container->make(Server::class);
    }

    /**
     * Set swoole server listeners.
     */
    protected function setSwooleServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->triggerEvent($event, func_get_args());
            };

            $this->getServer()->on($event, $callback);
        }
    }

    protected function prepareApplication()
    {
        if (!$this->app instanceof SwooleApp) {
            $this->app = new SwooleApp($this->container->getRootPath());
            $this->app->bind(SwooleApp::class, App::class);
            //绑定连接池
            if ($this->getConfig('pool.db.enable', true)) {
                $this->app->bind('db', Db::class);
            }
            if ($this->getConfig('pool.cache.enable', true)) {
                $this->app->bind('cache', Cache::class);
            }
            $this->app->initialize();
            $this->prepareConcretes();
        }
    }

    /**
     * 预加载
     */
    protected function prepareConcretes()
    {
        $defaultConcretes = ['db', 'cache', 'event'];

        $concretes = array_merge($defaultConcretes, $this->getConfig('concretes', []));

        foreach ($concretes as $concrete) {
            if ($this->app->has($concrete)) {
                $this->app->make($concrete);
            }
        }
    }

    /**
     * 清除apc、op缓存
     */
    protected function clearCache()
    {
        if (extension_loaded('apc')) {
            apc_clear_cache();
        }

        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    /**
     * Set process name.
     *
     * @param $process
     */
    protected function setProcessName($process)
    {
        // Mac OSX不支持进程重命名
        if (stristr(PHP_OS, 'DAR')) {
            return;
        }

        $serverName = 'swoole_http_server';
        $appName    = $this->container->config->get('app.name', 'ThinkPHP');

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        swoole_set_process_name($name);
    }

    /**
     * 热更新
     */
    protected function addHotUpdateProcess()
    {
        $process = new Process(function () {
            $watcher = new FileWatcher($this->getConfig('hot_update.include', []), $this->getConfig('hot_update.exclude', []), $this->getConfig('hot_update.name', []));

            $watcher->watch(function () {
                $this->getServer()->reload();
            });
        }, false, 0);

        $this->getServer()->addProcess($process);
    }

    /**
     * 在沙箱中执行
     * @param Closure $callable
     * @param null    $fd
     * @param bool    $persistent
     */
    protected function runInSandbox(Closure $callable, $fd = null, $persistent = false)
    {
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        $sandbox->run($callable, $fd, $persistent);
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->container->make(Handle::class);

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }
}
