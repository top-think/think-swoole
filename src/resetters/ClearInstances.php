<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\Sandbox;
use think\swoole\contract\ResetterInterface;

class ClearInstances implements ResetterInterface
{
    public function handle(Container $app, Sandbox $sandbox)
    {
        $instances = ['log'];

        $instances = array_merge($instances, $sandbox->getConfig()->get('swoole.instances', []));

        foreach ($instances as $instance) {
            $app->delete($instance);
        }

        return $app;
    }
}
