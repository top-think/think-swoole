<?php

namespace think\swoole\resetters;

use think\App;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

class ResetConfig implements ResetterInterface
{

    public function handle(App $app, Sandbox $sandbox)
    {
        $app->instance('config', clone $sandbox->getConfig());

        return $app;
    }
}
