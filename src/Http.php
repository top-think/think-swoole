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
    static $middleware;

    /** @var Route */
    static $route;

    static $apps = [];

    protected function loadMiddleware(): void
    {
        if (!isset(self::$middleware)) {
            parent::loadMiddleware();
            self::$middleware = clone $this->app->middleware;
        } else {
            $middleware = clone self::$middleware;
            $middleware->setApp($this->app);
            $this->app->instance("middleware", $middleware);
        }
    }

    protected function loadRoutes(): void
    {
        if ($this->isMulti()) {
            $route = &self::$apps[$this->name]['route'];
        } else {
            $route = &self::$route;
        }

        if (!isset($route)) {
            parent::loadRoutes();
            $route = clone $this->app->route;
        }
    }

    protected function dispatchToRoute($request)
    {
        if ($this->isMulti()) {
            $route = &self::$apps[$this->name]['route'];
        } else {
            $route = &self::$route;
        }

        if (isset($route)) {
            $newRoute = clone $route;
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

    protected function loadApp(string $appName): void
    {
        if (isset(self::$apps[$appName])) {
            $app = $this->app;

            $this->app->instance("config", clone self::$apps[$appName]['config']);

            $middleware = clone self::$apps[$appName]['middleware'];
            $middleware->setApp($app);
            $this->app->instance("middleware", $middleware);

            $event = clone self::$apps[$appName]['event'];
            $closure = function () use ($app) {
                $this->app = $app;
            };
            $resetEvent = $closure->bindTo($event, $event);
            $resetEvent();
            $this->app->instance("event", $event);

            $lang = clone self::$apps[$appName]['lang'];
            $request = $this->app->request;
            $closure = function () use ($request) {
                $this->request = $request;
            };
            $resetLang = $closure->bindTo($lang, $lang);
            $resetLang();
            $this->app->instance("lang", $lang);

            if (isset(self::$apps[$appName]['provider'])) {
                $this->app->bind(self::$apps[$appName]['provider']);
            }
        } else {
            parent::loadApp($appName);
            self::$apps[$appName]['config']     = clone $this->app->config;
            self::$apps[$appName]['middleware'] = clone $this->app->middleware;
            self::$apps[$appName]['event']      = clone $this->app->event;
            self::$apps[$appName]['lang']       = clone $this->app->lang;

            if (is_file($this->app->getAppPath() . 'provider.php')) {
                self::$apps[$appName]['provider'] = include $this->app->getAppPath() . 'provider.php';
            }
        }
    }
}
