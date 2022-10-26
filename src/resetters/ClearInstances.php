<?php

namespace think\swoole\resetters;

use think\App;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

class ClearInstances implements ResetterInterface
{
    public function handle(App $app, Sandbox $sandbox)
    {
        $instances = ['log', 'session', 'view', 'response', 'cookie'];

        $instances = array_merge($instances, $sandbox->getConfig()->get('swoole.instances', []));

        foreach ($instances as $instance) {
            $app->delete($instance);
        }

        return $app;
    }
}
