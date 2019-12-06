<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\concerns\ModifyProperty;
use think\swoole\Sandbox;
use think\swoole\contract\ResetterInterface;

/**
 * Class ResetEvent
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetEvent implements ResetterInterface
{
    use ModifyProperty;

    public function handle(Container $app, Sandbox $sandbox)
    {
        $event = clone $sandbox->getEvent();
        $this->modifyProperty($event, $app);
        $app->instance('event', $event);

        return $app;
    }
}
