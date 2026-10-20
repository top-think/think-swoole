<?php

namespace think\swoole\facade;

use think\Facade;
use think\swoole\Redis as RedisService;

/**
 * @see \think\swoole\Redis
 * @mixin \think\swoole\Redis
 * @method static \think\swoole\redis\Connection connection(?string $name = null)
 */
class Redis extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeClass()
    {
        return RedisService::class;
    }
}
