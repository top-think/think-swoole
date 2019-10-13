<?php

namespace think\swoole\concerns;

use Exception;
use Swoole\Runtime;
use Swoole\Server\Task;
use think\Event;
use think\swoole\PidManager;

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
        Runtime::enableCoroutine($this->getConfig('coroutine.enable', true), $this->getConfig('coroutine.flags', SWOOLE_HOOK_ALL));

        $this->clearCache();

        $this->setProcessName($server->taskworker ? 'task process' : 'worker process');

        $this->prepareApplication();

        $this->bindSwooleTable();

        $this->triggerEvent("workerStart");
    }

    /**
     * Set onTask listener.
     *
     * @param mixed $server
     * @param Task  $task
     */
    public function onTask($server, Task $task)
    {
        $this->runInSandbox(function (Event $event) use ($task) {
            $event->trigger('swoole.task', $task);
        });
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
        $this->triggerEvent('shutdown');
        $this->pidManager->remove();
    }

}
