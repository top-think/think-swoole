<?php

namespace think\swoole\pool;

use think\swoole\pool\proxy\Store;

class Cache extends \think\Cache
{
    protected function createDriver(string $name)
    {
        return new Store(function () use ($name) {
            return parent::createDriver($name);
        }, $this->app->config->get('swoole.pool.cache', []));
    }

}
