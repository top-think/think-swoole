<?php

namespace think\swoole\facade;

use think\Facade;
use think\swoole\websocket\Pusher as PusherService;

/**
 * @see \think\swoole\websocket\Pusher
 * @mixin \think\swoole\websocket\Pusher
 * @method static \think\swoole\websocket\Pusher to(...$values)
 * @method static void push($data, int $opcode = 1)
 * @method static void emit(string $event, ...$data)
 */
class Pusher extends Facade
{
    /**
     * Pusher 会记录本次推送目标，静态调用时始终创建独立实例。
     * @var bool
     */
    protected static $alwaysNewInstance = true;

    /**
     * @return string
     */
    protected static function getFacadeClass()
    {
        return PusherService::class;
    }
}
