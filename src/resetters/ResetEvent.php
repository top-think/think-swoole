<?php

namespace think\swoole\resetters;

use think\App;
use think\swoole\concerns\ModifyProperty;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

/**
 * Class ResetEvent
 * @package think\swoole\resetters
 */
class ResetEvent implements ResetterInterface
{
    use ModifyProperty;

    public function handle(App $app, Sandbox $sandbox)
    {
        $event = clone $sandbox->getEvent();
        $this->modifyProperty($event, $app);
        $app->instance('event', $event);

        return $app;
    }
}
