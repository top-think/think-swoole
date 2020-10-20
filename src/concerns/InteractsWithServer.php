<?php

namespace think\swoole\concerns;

use Exception;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Server\Task;
use think\App;
use think\console\Output;
use think\Event;
use think\exception\Handle;
use think\helper\Str;
use think\swoole\Coordinator;
use think\swoole\FileWatcher;
use Throwable;

/**
 * Trait InteractsWithServer
 * @package think\swoole\concerns
 * @property App $container
 */
trait InteractsWithServer
{

    /**
     * 启动服务
     */
    public function run(): void
    {
        $this->getServer()->set([
            'task_enable_coroutine' => true,
            'send_yield'            => true,
            'reload_async'          => true,
            'enable_coroutine'      => true,
            'max_request'           => 0,
            'task_max_request'      => 0,
        ]);
        $this->initialize();
        $this->triggerEvent('init');

        //热更新
        if ($this->getConfig('hot_update.enable', false)) {
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
     * "onStart" listener.
     */
    public function onStart()
    {
        $this->setProcessName('master process');

        $this->triggerEvent("start", func_get_args());
    }

    /**
     * The listener of "managerStart" event.
     *
     * @return void
     */
    public function onManagerStart()
    {
        $this->setProcessName('manager process');
        $this->triggerEvent("managerStart", func_get_args());
    }

    /**
     * "onWorkerStart" listener.
     *
     * @param \Swoole\Http\Server|mixed $server
     *
     * @throws Exception
     */
    public function onWorkerStart($server)
    {
        Runtime::enableCoroutine(
            $this->getConfig('coroutine.enable', true),
            $this->getConfig('coroutine.flags', SWOOLE_HOOK_ALL)
        );

        $this->clearCache();

        $this->setProcessName($server->taskworker ? 'task process' : 'worker process');

        $this->prepareApplication();

        $this->triggerEvent("workerStart", $this->app);

        $this->getCoordinator()->triggerEvent("workerStart");
    }

    /**
     * Set onTask listener.
     *
     * @param mixed $server
     * @param Task $task
     */
    public function onTask($server, Task $task)
    {
        $this->runInSandbox(function (Event $event) use ($task) {
            $event->trigger('swoole.task', $task);
        }, $task->id);
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->triggerEvent('shutdown');
    }

    /**
     * @return Coordinator
     */
    public function getCoordinator()
    {
        return $this->container->make(Coordinator::class);
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

    /**
     * 热更新
     */
    protected function addHotUpdateProcess()
    {
        $process = new Process(function () {
            $watcher = new FileWatcher(
                $this->getConfig('hot_update.include', []),
                $this->getConfig('hot_update.exclude', []),
                $this->getConfig('hot_update.name', [])
            );

            $watcher->watch(function () {
                $this->getServer()->reload();
            });
        }, false, 0);

        $this->addProcess($process);
    }

    /**
     * Add process to http server
     *
     * @param Process $process
     */
    public function addProcess(Process $process): void
    {
        $this->getServer()->addProcess($process);
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
