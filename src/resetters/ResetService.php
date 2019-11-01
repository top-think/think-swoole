<?php

namespace think\swoole\resetters;

use ReflectionObject;
use think\Container;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

/**
 * Class ResetService
 * @package think\swoole\resetters
 * @property Container $app;
 */
class ResetService implements ResetterInterface
{

    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox)
    {
        foreach ($sandbox->getServices() as $service) {
            $this->rebindServiceContainer($app, $service);
            if (method_exists($service, 'register')) {
                $service->register();
            }
            if (method_exists($service, 'boot')) {
                $app->invoke([$service, 'boot']);
            }
        }

        $reflectObject   = new ReflectionObject($app);
        $reflectProperty = $reflectObject->getProperty('services');
        $reflectProperty->setAccessible(true);
        $services = $reflectProperty->getValue($app);

        foreach ($services as $service) {
            $this->rebindServiceContainer($app, $service);
        }
    }

    protected function rebindServiceContainer($app, $service)
    {
        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetService = $closure->bindTo($service, $service);
        $resetService();
    }
}
