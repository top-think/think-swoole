<?php

namespace think\swoole\concerns;

use Swoole\Constant;
use Swoole\Coroutine;
use Swoole\Event;
use Swoole\Process;
use Swoole\Process\Pool;
use Swoole\Runtime;
use think\App;
use think\swoole\FileWatcher;

/**
 * Trait InteractsWithServer
 * @package think\swoole\concerns
 * @property App $container
 */
trait InteractsWithServer
{

    /**
     * @var array
     */
    protected $startFuncMap = [];

    protected $workerId;

    /** @var Pool */
    protected $pool;

    public function addBatchWorker(int $workerNum, callable $func, $name = null)
    {
        for ($i = 0; $i < $workerNum; $i++) {
            $this->addWorker($func, $name ? "{$name} #{$i}" : null);
        }
        return $this;
    }

    public function addWorker(callable $func, $name = null): self
    {
        $this->startFuncMap[] = [$func, $name];
        return $this;
    }

    /**
     * 启动服务
     * @param string $envName 环境变量标识
     */
    public function start(string $envName): void
    {
        $this->setProcessName('manager process');

        $this->initialize();
        $this->triggerEvent('init');

        //热更新
        if ($this->getConfig('hot_update.enable', false)) {
            $this->addHotUpdateProcess();
        }

        $pool = new Pool(count($this->startFuncMap), SWOOLE_IPC_UNIXSOCK, 0, true);

        $pool->on(Constant::EVENT_WORKER_START, function ($pool, $workerId) use ($envName) {

            Runtime::enableCoroutine();

            $this->pool     = $pool;
            $this->workerId = $workerId;

            [$func, $name] = $this->startFuncMap[$workerId];

            if ($name) {
                $this->setProcessName($name);
            }

            Process::signal(SIGTERM, function () {
                $this->pool->getProcess()->exit();
            });

            /** @var Coroutine\Socket $socket */
            $socket = $this->pool->getProcess()->exportSocket();

            //启动消息监听
            Event::add($socket, function (Coroutine\Socket $socket) {
                $recv    = $socket->recv();
                $message = unserialize($recv);
                $this->triggerEvent('message', $message);
            });

            $this->clearCache();
            $this->prepareApplication($envName);

            $this->triggerEvent(Constant::EVENT_WORKER_START);

            $func($pool, $workerId);
        });

        $pool->start();
    }

    public function getWorkerId()
    {
        return $this->workerId;
    }

    public function sendMessage($workerId, $message)
    {
        if ($workerId === $this->workerId) {
            $this->triggerEvent('message', $message);
        } else {
            /** @var Process $process */
            $process = $this->pool->getProcess($workerId);
            $socket  = $process->exportSocket();
            $socket->send(serialize($message));
        }
    }

    public function runWithBarrier(callable $func, ...$params)
    {
        $channel = new Coroutine\Channel(1);

        Coroutine::create(function (...$params) use ($channel, $func) {

            call_user_func_array($func, $params);

            $channel->close();
        }, ...$params);

        $channel->pop();
    }

    /**
     * 热更新
     */
    protected function addHotUpdateProcess()
    {
        $this->addWorker(function (Process\Pool $pool) {
            $watcher = new FileWatcher(
                $this->getConfig('hot_update.include', []),
                $this->getConfig('hot_update.exclude', []),
                $this->getConfig('hot_update.name', [])
            );

            $watcher->watch(function () use ($pool) {
                Process::kill($pool->master_pid, SIGUSR1);
            });
        }, 'hot update');
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
        $appName = $this->container->config->get('app.name', 'ThinkPHP');

        $name = sprintf('swoole: %s process for %s', $process, $appName);

        @cli_set_process_title($name);
    }
}
