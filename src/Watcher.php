<?php

namespace think\swoole;

use think\swoole\watcher\Driver;

/**
 * @mixin Driver
 */
class Watcher extends \think\Manager
{
    protected $namespace = '\\think\\swoole\\watcher\\';

    protected function getConfig(string $name, $default = null)
    {
        return $this->app->config->get('swoole.hot_update.' . $name, $default);
    }

    protected function resolveParams($name): array
    {
        return [
            $this->getConfig('include', []),
            $this->getConfig('exclude', []),
            $this->getConfig('name', []),
        ];
    }

    public function getDefaultDriver()
    {
        return $this->getConfig('type', 'scan');
    }
}
