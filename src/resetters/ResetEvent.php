<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\Sandbox;

class ResetEvent implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $app->instance('event', clone $sandbox->getEvent());

        return $app;
    }
}
