<?php

namespace think\swoole\queue;

class Worker extends \think\queue\Worker
{
    protected function supportsAsyncSignals()
    {
        return false;
    }
}
