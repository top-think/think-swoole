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

    protected function addBatchWorker(int $workerNum, callable $func)
    {
        for ($i = 0; $i < $workerNum; $i++) {
            $this->startFuncMap[] = $func;
        }
        return $this;
    }

    protected function addWorker(callable $func): self
    {
        $this->addBatchWorker(1, $func);
        return $this;
    }

    /**
     * 启动服务
     */
    public function start(): void
    {
        Runtime::enableCoroutine();

        $this->initialize();
        $this->triggerEvent('init');
        $this->setProcessName('manager process');

        //热更新
        if ($this->getConfig('hot_update.enable', false)) {
            $this->addHotUpdateProcess();
        }

        $pool = new Pool(count($this->startFuncMap), SWOOLE_IPC_UNIXSOCK, null, true);

        $pool->on(Constant::EVENT_WORKER_START, function ($pool, $workerId) {
            $this->pool     = $pool;
            $this->workerId = $workerId;

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
            $this->prepareApplication();

            $this->triggerEvent(Constant::EVENT_WORKER_START);

            $this->startFuncMap[$workerId]($pool, $workerId);
        });

        $pool->start();
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

    /**
     * 热更新
     */
    protected function addHotUpdateProcess()
    {
        $this->addWorker(function (Process\Pool $pool) {
            $this->setProcessName('hot update process');

            $watcher = new FileWatcher(
                $this->getConfig('hot_update.include', []),
                $this->getConfig('hot_update.exclude', []),
                $this->getConfig('hot_update.name', [])
            );

            $watcher->watch(function () use ($pool) {
                Process::kill($pool->master_pid, SIGUSR1);
            });
        });
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
        $serverName = 'swoole';
        $appName    = $this->container->config->get('app.name', 'ThinkPHP');

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        @cli_set_process_title($name);
    }
}
