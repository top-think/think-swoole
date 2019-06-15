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

use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Runtime;
use think\App;
use think\console\Output;
use think\Container;
use think\exception\Handle;
use think\helper\Str;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\concerns\InteractsWithWebsocket;
use think\swoole\facade\Server;
use think\swoole\helper\Build;
use think\swoole\helper\SuperClosure;
use think\swoole\helper\Task;
use think\swoole\helper\Timer;
use think\swoole\interfaces\RunInterface;
use think\swoole\interfaces\TimerInterface;
use Throwable;
use XCron\CronExpression;

/**
 * Class Manager
 */
class Swoole
{
    use InteractsWithSwooleTable, InteractsWithWebsocket;
    
    /**
     * @var Container
     */
    protected $container;
    
    /**
     * @var App
     */
    protected $app;
    
    /**
     * Server events.
     *
     * @var array
     */
    protected $events
        = [
            'start',
            'shutDown',
            'workerStart',
            'workerStop',
            'packet',
            'bufferFull',
            'bufferEmpty',
            'task',
            'finish',
            'pipeMessage',
            'workerError',
            'managerStart',
            'managerStop',
            'request',
        ];
    
    /**
     * Manager constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->initialize();
    }
    
    /**
     * Run swoole server.
     */
    public function run()
    {
        $this->container->make(Server::class)->start();
    }
    
    /**
     * Stop swoole server.
     */
    public function stop()
    {
        $this->container->make(Server::class)->shutdown();
    }
    
    /**
     * Initialize.
     */
    protected function initialize()
    {
        $this->createTables();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
    }
    
    /**
     * Set swoole server listeners.
     */
    protected function setSwooleServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->container->event->trigger("swoole.$event", func_get_args());
            };
            
            $this->container->make(Server::class)->on($event, $callback);
        }
    }
    
    /**
     * "onStart" listener.
     */
    public function onStart()
    {
        $this->setProcessName('master process');
        $this->createPidFile();
        
        $this->container->event->trigger('swoole.start', func_get_args());
    }
    
    /**
     * The listener of "managerStart" event.
     *
     * @return void
     */
    public function onManagerStart()
    {
        $this->setProcessName('manager process');
        $this->container->event->trigger('swoole.managerStart', func_get_args());
    }
    
    /**
     * @param \Swoole\Http\Server|mixed $server
     * @param int $worker_id
     * @throws Exception
     */
    public function onWorkerStart($server, int $worker_id)
    {
        if ($this->container->config->get('swoole.enable_coroutine', false)) {
            Runtime::enableCoroutine(true);
        }
        
        $this->clearCache();
        
        $this->container->event->trigger('swoole.workerStart', func_get_args());
        
        // don't init app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task process');
            
            return;
        }
        
        $this->setProcessName('worker process');
        
        $this->prepareApplication();
        
        if ($this->isServerWebsocket) {
            $this->prepareWebsocketHandler();
            $this->loadWebsocketRoutes();
        }
        $this->prepareTimer($server, $worker_id);
    }
    
    protected function prepareTimer($server, $worker_id)
    {
        if (0 == $worker_id) {
            $this->timerLists = (new Timer($this->app->make(Build::class), $this->app))->getTimerLists();
            \Swoole\Timer::tick(500, function () use ($server) {
                $sandbox = $this->app->make(Sandbox::class);
                try {
                    $sandbox->init();
                    $sandbox->runService(function (App $app) use ($server) {
                        foreach ($this->timerLists as &$timer) {
                            $nexttime = $timer['nexttime'];
                            $class    = $timer['className'];
                            $classObj = $timer['classObj'];
                            if ($nexttime < time()) {
                                $interval = $classObj->getInterval();
                                $task     = $app->make(Task::class);
                                $task->async(function () use ($class) {
                                    return $class;
                                });
                                if (is_string($interval) && !is_numeric($interval)) {
                                    $cron              = CronExpression::factory($interval);
                                    $timer['nexttime'] = $cron->getNextRunDate()->getTimestamp();
                                }
                                if (is_int($interval)) {
                                    $timer['nexttime'] = time() + $interval;
                                }
                            }
                        }
                    });
                } catch (Throwable $e) {
                    $this->logServerError($e);
                } finally {
                    $sandbox->clear();
                }
            });
        }
    }
    
    protected function prepareApplication()
    {
        if (!$this->app instanceof App) {
            $this->app = new App();
            $this->app->initialize();
        }
        
        $this->bindSandbox();
        $this->bindSwooleTable();
        
        if ($this->isServerWebsocket) {
            $this->bindRoom();
            $this->bindWebsocket();
        }
    }
    
    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];
        
        if (isset($header['x-requested-with'])) {
            $server['HTTP_X_REQUESTED_WITH'] = $header['x-requested-with'];
        }
        
        if (isset($header['referer'])) {
            $server['http_referer'] = $header['referer'];
        }
        
        if (isset($header['host'])) {
            $server['http_host'] = $header['host'];
        }
        
        // 重新实例化请求对象 处理swoole请求数据
        /** @var \think\Request $request */
        $request = $this->app->make('request', [], true);
        
        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . (!empty($req->server['query_string']) ? '&' . $req->server['query_string'] : ''))
            ->setPathinfo(ltrim($req->server['path_info'], '/'));
    }
    
    protected function sendResponse(Sandbox $sandbox, \think\Response $thinkResponse, \Swoole\Http\Response $swooleResponse)
    {
        
        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }
        
        // 发送状态码
        $swooleResponse->status($thinkResponse->getCode());
        
        foreach ($sandbox->getApplication()->cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;
            
            $swooleResponse->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }
        
        $content = $thinkResponse->getContent();
        
        if (!empty($content)) {
            $swooleResponse->write($content);
        }
        
        $swooleResponse->end();
    }
    
    /**
     * "onRequest" listener.
     *
     * @param Request $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->app->event->trigger('swoole.request');
        
        $this->resetOnRequest();
        
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);
        $request = $this->prepareRequest($req);
        
        try {
            $sandbox->setRequest($request);
            
            $sandbox->init();
            
            $response = $sandbox->run($request);
            
            $this->sendResponse($sandbox, $response, $res);
        } catch (Throwable $e) {
            try {
                $exceptionResponse = $this->app
                    ->make(Handle::class)
                    ->render($request, $e);
                
                $this->sendResponse($sandbox, $exceptionResponse, $res);
            } catch (Throwable $e) {
                $this->logServerError($e);
            }
        } finally {
            $sandbox->clear();
        }
    }
    
    /**
     * Reset on every request.
     */
    protected function resetOnRequest()
    {
        // Reset websocket data
        if ($this->isServerWebsocket) {
            $this->app->make(Websocket::class)->reset(true);
        }
    }
    
    /**
     * Set onTask listener.
     *
     * @param mixed $server
     * @param string|Task $taskId or $task
     * @param string $srcWorkerId
     * @param mixed $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {
        $this->prepareApplication();
        
        if ($this->isServerWebsocket) {
            $this->prepareWebsocketHandler();
            $this->loadWebsocketRoutes();
        }
        
        $this->container->event->trigger('swoole.task', func_get_args());
        
        try {
            if ($data instanceof SuperClosure) {
                $className       = $data();
                $ReflectionClass = new \ReflectionClass($className);
                $Interfaces      = $ReflectionClass->getInterfaces();
                if (isset($Interfaces[RunInterface::class])) {
                    $obj = $this->app->make($className);
                    if (is_object($obj) && $obj instanceof RunInterface) {
                        $obj->run($server);
                    }
                }
                return;
            }
            // push websocket message
            if ($this->isWebsocketPushPayload($data)) {
                $this->pushMessage($server, $data['data']);
                // push async task to queue
            }
        } catch (Throwable $e) {
            $this->logServerError($e);
        }
    }
    
    /**
     * Set onFinish listener.
     *
     * @param mixed $server
     * @param string $taskId
     * @param mixed $data
     */
    public function onFinish($server, $taskId, $data)
    {
        // task worker callback
        $this->container->event->trigger('swoole.finish', func_get_args());
        
        return;
    }
    
    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->removePidFile();
    }
    
    /**
     * Bind sandbox to Laravel app container.
     */
    protected function bindSandbox()
    {
        $this->app->bind(Sandbox::class, function (App $app) {
            return new Sandbox($app);
        });
        
        $this->app->bind('swoole.sandbox', Sandbox::class);
    }
    
    /**
     * Gets pid file path.
     *
     * @return string
     */
    protected function getPidFile()
    {
        return $this->container->make('config')->get('swoole.server.options.pid_file');
    }
    
    /**
     * Create pid file.
     */
    protected function createPidFile()
    {
        $pidFile = $this->getPidFile();
        $pid     = $this->container->make(Server::class)->master_pid;
        
        file_put_contents($pidFile, $pid);
    }
    
    /**
     * Remove pid file.
     */
    protected function removePidFile()
    {
        $pidFile = $this->getPidFile();
        
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }
    
    /**
     * Clear APC or OPCache.
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
     * @codeCoverageIgnore
     *
     * @param $process
     */
    protected function setProcessName($process)
    {
        
        $serverName = 'swoole_http_server';
        $appName    = $this->container->config->get('app.name', 'ThinkPHP');
        
        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);
        
        @swoole_set_process_name($name);
    }
    
    /**
     * Add process to http server
     *
     * @param Process $process
     */
    public function addProcess(Process $process): void
    {
        $this->container->make(Server::class)->addProcess($process);
    }
    
    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->app->make(Handle::class);
        
        $handle->renderForConsole(new Output(), $e);
        
        $handle->report($e);
    }
}
