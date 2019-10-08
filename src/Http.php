<?php

namespace think\swoole;

use think\Middleware;
use think\Route;

/**
 * Class Http
 * @package think\swoole
 * @property $request
 */
class Http extends \think\Http
{
    /** @var Middleware */
    protected static $middleware;

    /** @var Route */
    protected static $route;

    protected function loadMiddleware(): void
    {
        if (!isset(self::$middleware)) {
            parent::loadMiddleware();
            self::$middleware = clone $this->app->middleware;
        }

        $middleware = clone self::$middleware;

        $app     = $this->app;
        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetMiddleware = $closure->bindTo($middleware, $middleware);
        $resetMiddleware();

        $this->app->instance("middleware", $middleware);
    }

    protected function loadRoutes(): void
    {
        if (!isset(self::$route)) {
            parent::loadRoutes();
            self::$route = clone $this->app->route;
        }
    }

    protected function dispatchToRoute($request)
    {
        if (isset(self::$route)) {
            $newRoute = clone self::$route;
            $app      = $this->app;
            $closure  = function () use ($app) {
                $this->app = $app;
            };

            $resetRouter = $closure->bindTo($newRoute, $newRoute);
            $resetRouter();

            $this->app->instance("route", $newRoute);
        }

        return parent::dispatchToRoute($request);
    }
}
