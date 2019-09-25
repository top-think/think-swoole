<?php

namespace think\swoole\pool;

use think\swoole\concerns\InteractsWithPool;
use think\swoole\coroutine\Context;
use think\swoole\pool\cache\Store;

class Cache extends \think\Cache
{
    use InteractsWithPool;

    protected function getMaxActive()
    {
        return $this->app->config->get('swoole.pool.cache.max_active', 3);
    }

    protected function getMaxWaitTime()
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

            $pool = $this->getPool($name);

            if (!isset($this->connectionCount[$name])) {
                $this->connectionCount[$name] = 0;
            }

            if ($this->connectionCount[$name] < $this->getMaxActive()) {
                //新建
                $this->connectionCount[$name]++;
                return new Store($this->createDriver($name), $pool);
            }

            $store = $pool->pop($this->getMaxWaitTime());

            if ($store === false) {
                throw new \RuntimeException(sprintf(
                    'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                    $this->getMaxWaitTime(),
                    $pool->length(),
                    $this->connectionCount[$name] ?? 0
                ));
            }

            return new Store($store, $pool);
        });
    }
}
