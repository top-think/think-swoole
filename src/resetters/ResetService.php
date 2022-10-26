<?php

namespace think\swoole\resetters;

use think\App;
use think\swoole\concerns\ModifyProperty;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

/**
 * Class ResetService
 * @package think\swoole\resetters
 */
class ResetService implements ResetterInterface
{
    use ModifyProperty;

    /**
     * "handle" function for resetting app.
     *
     * @param App $app
     * @param Sandbox $sandbox
     */
    public function handle(App $app, Sandbox $sandbox)
    {
        foreach ($sandbox->getServices() as $service) {
            $this->modifyProperty($service, $app);
            if (method_exists($service, 'register')) {
                $service->register();
            }
            if (method_exists($service, 'boot')) {
                $app->invoke([$service, 'boot']);
            }
        }
    }

}
