<?php

namespace think\swoole\websocket;

use think\Manager;
use think\swoole\websocket\room\Table;

/**
 * Class Room
 * @package think\swoole\websocket
 * @mixin Table
 */
class Room extends Manager
{
    protected $namespace = "\\think\\swoole\\websocket\\room\\";

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("swoole.websocket.room.{$name}", []);
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->app->config->get('swoole.websocket.room.type', 'table');
    }
}
