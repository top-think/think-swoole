<?php

namespace think\swoole\ipc;

use Swoole\Process\Pool;
use think\swoole\Manager;

abstract class Driver
{
    /** @var array */
    protected $config;

    /** @var Manager */
    protected $manager;

    protected $workerId;

    public function __construct(Manager $manager, array $config)
    {
        $this->manager = $manager;
        $this->config  = $config;
    }

    public function listenMessage($workerId)
    {
        $this->workerId = $workerId;

        $this->subscribe();
    }

    public function sendMessage($workerId, $message, ?string $nodeId = null)
    {
        // 同一节点 && 同一 worker → 直接触发；其余 → IPC 发布
        if (($nodeId === null || $nodeId === $this->manager->getNodeId()) && $workerId === $this->workerId) {
            $this->manager->triggerEvent('message', $message);
        } else {
            $this->publish($workerId, $message, $nodeId);
        }
    }

    abstract public function getType();

    abstract public function prepare(Pool $pool);

    abstract public function subscribe();

    abstract public function publish($workerId, $message, ?string $nodeId = null);
}
