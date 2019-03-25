<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\Sandbox;

class ResetSession implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        return $app;
    }
}
