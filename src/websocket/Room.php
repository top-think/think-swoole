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

    /**
     * 判断房间是否为空
     *
     * @param string $room
     *
     * @return bool
     */
    public function isEmpty(string $room): bool
    {
        return empty($this->getClients($room));
    }

    /**
     * 获取房间内的客户端数量
     *
     * @param string $room
     *
     * @return int
     */
    public function getClientsCount(string $room): int
    {
        return count($this->getClients($room));
    }

    /**
     * 判断某个 fd 是否在房间内
     *
     * @param string $fd
     * @param string $room
     *
     * @return bool
     */
    public function isInRoom(string $fd, string $room): bool
    {
        return in_array($fd, $this->getClients($room));
    }
}
