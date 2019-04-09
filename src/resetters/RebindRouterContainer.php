<?php

namespace think\swoole\resetters;

use think\Container;
use think\Route;
use think\swoole\Sandbox;

/**
 * Class RebindRouterContainer
 * @package think\swoole\resetters
 * @property Container $app
 */
class RebindRouterContainer implements ResetterContract
{

    protected $container;

    /**
     * @var mixed
     */
    protected $routes;

    public function handle(Container $app, Sandbox $sandbox)
    {
        $route = $app->make(Route::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetRouter = $closure->bindTo($route, $route);
        $resetRouter();

        return $app;
    }
}
