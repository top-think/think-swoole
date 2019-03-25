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
use think\App;
use think\console\Output;
use think\Container;
use think\exception\Handle;
use think\helper\Str;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\facade\Server;
use Throwable;

/**
 * Class Manager
 */
class Swoole
{
    use InteractsWithSwooleTable;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var string
     */
    protected $basePath;

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
                $this->container->make('event')->trigger("swoole.$event", func_get_args());
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
     * "onWorkerStart" listener.
     *
     * @param \Swoole\Http\Server|mixed $server
     *
     * @throws Exception
     */
    public function onWorkerStart($server)
    {
        $this->clearCache();

        $this->container->event->trigger('swoole.workerStart', func_get_args());

        // don't init laravel app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task process');

            return;
        }

        $this->setProcessName('worker process');

        $this->prepareApplication();
    }

    protected function prepareApplication()
    {
        if (!$this->app instanceof App) {
            $this->app = new App();
        }

        $this->bindSandbox();
        $this->bindSwooleTable();
    }

    /**
     * "onRequest" listener.
     *
     * @param Request  $swooleRequest
     * @param Response $swooleResponse
     */
    public function onRequest($swooleRequest, $swooleResponse)
    {
        $this->app->event->trigger('swoole.request');

        $this->resetOnRequest();
        $sandbox      = $this->app->make(Sandbox::class);
        $handleStatic = $this->container->config->get('swoole.server.handle_static_files', true);
        $publicPath   = $this->container->config->get('swoole.server.public_path', root_path('public'));

        try {
            // handle static file request first
            if ($handleStatic && Request::handleStatic($swooleRequest, $swooleResponse, $publicPath)) {
                return;
            }
            // transform swoole request to illuminate request
            $illuminateRequest = Request::make($swooleRequest)->toIlluminate();

            // set current request to sandbox
            $sandbox->setRequest($illuminateRequest);

            // enable sandbox
            $sandbox->enable();

            // handle request via laravel/lumen's dispatcher
            $illuminateResponse = $sandbox->run($illuminateRequest);

            // send response
            Response::make($illuminateResponse, $swooleResponse)->send();
        } catch (Throwable $e) {
            try {
                $exceptionResponse = $this->app
                    ->make(ExceptionHandler::class)
                    ->render(
                        $illuminateRequest,
                        $this->normalizeException($e)
                    );
                Response::make($exceptionResponse, $swooleResponse)->send();
            } catch (Throwable $e) {
                $this->logServerError($e);
            }
        } finally {
            // disable and recycle sandbox resource
            $sandbox->disable();
        }
    }

    /**
     * Reset on every request.
     */
    protected function resetOnRequest()
    {

    }

    /**
     * Set onTask listener.
     *
     * @param mixed                      $server
     * @param string|\Swoole\Server\Task $taskId or $task
     * @param string                     $srcWorkerId
     * @param mixed                      $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {

    }

    /**
     * Set onFinish listener.
     *
     * @param mixed  $server
     * @param string $taskId
     * @param mixed  $data
     */
    public function onFinish($server, $taskId, $data)
    {
        // task worker callback
        $this->container->make('events')->dispatch('swoole.finish', func_get_args());

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

        swoole_set_process_name($name);
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
        $handle = $this->app['error_handle'];

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }

    /**
     * Indicates if the payload is async task.
     *
     * @param mixed $payload
     *
     * @return boolean
     */
    protected function isAsyncTaskPayload($payload): bool
    {
        $data = json_decode($payload, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        return isset($data['job']);
    }
}
