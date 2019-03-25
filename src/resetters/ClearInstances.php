<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\Sandbox;

class ClearInstances implements ResetterContract
{
    public function handle(Container $app, Sandbox $sandbox)
    {
        $instances = $sandbox->getConfig()->get('swoole.instances', []);

        foreach ($instances as $instance) {
            $app->delete($instance);
        }

        return $app;
    }
}
