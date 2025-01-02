<?php

namespace think\swoole\contract;

interface WatcherInterface
{
    public function watch(callable $callback);
}
