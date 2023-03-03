<?php

namespace think\swoole\coroutine;

use Swoole\Coroutine;

class Barrier
{
    public static function run(callable $func, ...$params)
    {
        $channel = new Coroutine\Channel(1);

        Coroutine::create(function (...$params) use ($channel, $func) {
            Coroutine::defer(function () use ($channel) {
                $channel->close();
            });

            call_user_func_array($func, $params);
        }, ...$params);

        $channel->pop();
    }
}
