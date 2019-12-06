<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\concerns\ModifyProperty;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

/**
 * Class ResetService
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetService implements ResetterInterface
{
    use ModifyProperty;

    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox)
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
