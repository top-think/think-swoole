<?php

namespace think\swoole;

use think\Middleware;
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

    /** @var Middleware[] */
    protected static $middleware;

    /** @var Route[] */
    protected static $route;

    protected function loadMiddleware(): void
    {
        if (!isset(self::$middleware[$this->app->http->name])) {
            parent::loadMiddleware();
            self::$middleware[$this->app->http->name] = clone $this->app->middleware;
            $this->modifyProperty(self::$middleware[$this->app->http->name], null);
        }

        $middleware = clone self::$middleware[$this->app->http->name];
        $this->modifyProperty($middleware, $this->app);
        $this->app->instance("middleware", $middleware);
    }

    protected function loadRoutes(): void
    {
        if (!isset(self::$route[$this->app->http->name])) {
            parent::loadRoutes();
            self::$route[$this->app->http->name] = clone $this->app->route;
            $this->modifyProperty(self::$route[$this->app->http->name], null);
            $this->modifyProperty(self::$route[$this->app->http->name], null, 'request');
        }
    }

    protected function dispatchToRoute($request)
    {
        if (isset(self::$route[$this->app->http->name])) {
            $newRoute = clone self::$route[$this->app->http->name];
            $this->modifyProperty($newRoute, $this->app);
            $this->app->instance("route", $newRoute);
        }

        return parent::dispatchToRoute($request);
    }

}
