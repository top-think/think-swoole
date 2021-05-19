<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Http\Server;
use Swoole\Process;
use Swoole\Server\Task;
use think\App;
use think\Event;
use think\swoole\FileWatcher;
use think\swoole\Job;

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
    public function start(): void
    {
        $pm = new Process\Manager();

        $pm->addBatch(swoole_cpu_num(), [$this, 'onWorkerStart'], true);

        $this->initialize();
        $this->triggerEvent('init');

        //热更新
        if ($this->getConfig('hot_update.enable', false)) {
            $this->addHotUpdateProcess($pm);
        }

        $pm->start();
    }

    public function onWorkerStart(Process\Pool $pool, $workerId)
    {
        $this->clearCache();
        $this->setProcessName('worker process');
        $this->prepareApplication();

        $host = $this->getConfig('server.host');
        $port = $this->getConfig('server.port');

        $server = new Server($host, $port, false, true);

        $this->triggerEvent('workerStart', $server);

        $server->start();
    }

    /**
     * 热更新
     */
    protected function addHotUpdateProcess(Process\Manager $pm)
    {
        $pm->add(function () {
            $watcher = new FileWatcher(
                $this->getConfig('hot_update.include', []),
                $this->getConfig('hot_update.exclude', []),
                $this->getConfig('hot_update.name', [])
            );

            $watcher->watch(function () {
                //TODO 重启worker进程
            });
        }, true);
    }

    /**
     * Set onTask listener.
     *
     * @param mixed $server
     * @param Task $task
     */
    public function onTask($server, Task $task)
    {
        $this->runInSandbox(function (Event $event, App $app) use ($task) {
            if ($task->data instanceof Job) {
                $task->data->run($app);
            } else {
                $event->trigger('swoole.task', $task);
            }
        }, $task->id);
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
        $serverName = 'swoole server';
        $appName    = $this->container->config->get('app.name', 'ThinkPHP');

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        @cli_set_process_title($name);
    }
}
