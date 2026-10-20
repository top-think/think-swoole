<?php

namespace think\swoole\contract;

interface LockInterface
{
    public const RETRY_INTERVAL = 0.25;

    public function prepare();

    public function lock($name, $expire = 60);

    public function unlock($name);
}
