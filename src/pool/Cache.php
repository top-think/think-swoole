<?php

namespace think\swoole\pool;

use think\swoole\concerns\InteractsWithPool;
use think\swoole\coroutine\Context;

class Cache extends \think\Cache
{
    use InteractsWithPool;

    protected function getPoolMaxActive($name): int
    {
        return $this->app->config->get('swoole.pool.cache.max_active', 3);
    }

    protected function getPoolMaxWaitTime($name): int
    {
        return $this->app->config->get('swoole.pool.cache.max_wait_time', 3);
    }

    /**
     * 获取驱动实例
     * @param null|string $name
     * @return mixed
     */
    protected function driver(string $name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return Context::rememberData("cache.store.{$name}", function () use ($name) {
            return $this->getPoolConnection($name);
        });
    }

    protected function createPoolConnection(string $name)
    {
        return $this->createDriver($name);
    }
}
