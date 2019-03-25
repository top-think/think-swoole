<?php

namespace think\swoole\resetters;

use think\Container;
use think\Request;
use think\swoole\Sandbox;

class BindRequest implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $request = $sandbox->getRequest();

        if ($request instanceof Request) {
            $app->instance('request', $request);
        }

        return $app;
    }
}
