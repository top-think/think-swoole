<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

class ResetConfig implements ResetterInterface
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $app->instance('config', clone $sandbox->getConfig());

        return $app;
    }
}
