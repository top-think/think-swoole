<?php

namespace think\swoole\concerns;

use Exception;
use Swoole\Runtime;
use Swoole\Server\Task;
use think\swoole\PidManager;
use Throwable;

/**
 * Trait InteractsWithServer
 * @package think\swoole\concerns
 * @property PidManager $pidManager
 */
trait InteractsWithServer
{

    /**
     * "onStart" listener.
     */
    public function onStart()
    {
        $this->setProcessName('master process');
        $this->pidManager->create($this->getServer()->master_pid, $this->getServer()->manager_pid ?? 0);

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
        if ($this->getConfig('enable_coroutine', false)) {
            Runtime::enableCoroutine(true);
        }

        $this->clearCache();

        $this->triggerEvent("workerStart", func_get_args());

        // don't init app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task process');

            return;
        }

        $this->setProcessName('worker process');

        $this->prepareApplication();
    }

    /**
     * Set onTask listener.
     *
     * @param mixed       $server
     * @param string|Task $taskId or $task
     * @param string      $srcWorkerId
     * @param mixed       $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {
        $this->triggerEvent('task', func_get_args());

        try {
            if ($this->isWebsocketPushPayload($data)) {
                $this->pushMessage($server, $data['data']);
            }
            //todo other tasks

        } catch (Throwable $e) {
            $this->logServerError($e);
        }
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
        $this->triggerEvent('finish', func_get_args());
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->triggerEvent('shutdown', func_get_args());
        $this->pidManager->remove();
    }

}
