<?php

namespace think\swoole;

use Closure;
use InvalidArgumentException;
use think\App;
use think\Pipeline;

class Middleware
{
    /**
     * 中间件执行队列
     * @var array
     */
    protected $queue = [];

    /**
     * @var App
     */
    protected $app;

    public function __construct(App $app, $middlewares = [])
    {
        $this->app = $app;

        foreach ($middlewares as $middleware) {
            $this->queue[] = $this->buildMiddleware($middleware);
        }
    }

    public static function make(App $app, $middlewares = [])
    {
        return new self($app, $middlewares);
    }

    /**
     * 调度管道
     * @return Pipeline
     */
    public function pipeline()
    {
        return (new Pipeline())
            ->through(array_map(function ($middleware) {
                return function ($request, $next) use ($middleware) {
                    [$call, $params] = $middleware;

                    if (is_array($call) && is_string($call[0])) {
                        $call = [$this->app->make($call[0]), $call[1]];
                    }
                    return call_user_func($call, $request, $next, ...$params);
                };
            }, $this->queue));
    }

    /**
     * 解析中间件
     * @param mixed $middleware
     * @return array
     */
    protected function buildMiddleware($middleware): array
    {
        if (is_array($middleware)) {
            [$middleware, $params] = $middleware;
        }

        if ($middleware instanceof Closure) {
            return [$middleware, $params ?? []];
        }

        if (!is_string($middleware)) {
            throw new InvalidArgumentException('The middleware is invalid');
        }

        return [[$middleware, 'handle'], $params ?? []];
    }
}
