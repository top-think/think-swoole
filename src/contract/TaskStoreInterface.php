<?php

namespace think\swoole\contract;

interface TaskStoreInterface
{
    /**
     * 创建任务心跳
     */
    public function start(string $id, string $owner, int $leaseTtl): bool;

    /**
     * 续期任务心跳
     */
    public function heartbeat(string $id, string $owner, int $leaseTtl): bool;

    /**
     * 删除任务心跳
     */
    public function stop(string $id, string $owner): bool;

    /**
     * 判断任务心跳是否存在
     */
    public function exists(string $id): bool;
}
