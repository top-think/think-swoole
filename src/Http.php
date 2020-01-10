<?php

namespace think\swoole;

use think\Middleware;
use think\Response;
use think\Route;
use think\swoole\concerns\ModifyProperty;

/**
 * Class Http
 * @package think\swoole
 * @property $request
 */
class Http extends \think\Http
{
    use ModifyProperty;

    /** @var Middleware */
    protected static $middleware;

    /** @var Route */
    protected static $route;

    protected function loadMiddleware(): void
    {
        if (!isset(self::$middleware)) {
            parent::loadMiddleware();
            self::$middleware = clone $this->app->middleware;
            $this->modifyProperty(self::$middleware, null);
        }

        $middleware = clone self::$middleware;
        $this->modifyProperty($middleware, $this->app);
        $this->app->instance("middleware", $middleware);
    }

    protected function loadRoutes(): void
    {
        if (!isset(self::$route)) {
            parent::loadRoutes();
            self::$route = $this->app->route;
        }
    }

    protected function dispatchToRoute($request)
    {
        if (isset(self::$route)) {
            $newRoute = self::$route;
            $this->modifyProperty($newRoute, $this->app);
            $this->app->instance("route", $newRoute);
        }

        return parent::dispatchToRoute($request);
    }

    public function end(Response $response): void
    {
        parent::end($response);

        $this->modifyProperty(self::$route, null);
        $this->modifyProperty(self::$route, null, 'request');
    }
}
