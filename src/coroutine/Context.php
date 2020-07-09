<?php

namespace think\swoole\coroutine;

use Closure;
use Swoole\Coroutine;

class Context
{

    public static function getData(string $key, $default = null)
    {
        return Coroutine::getContext()['data'][$key] ?? $default;
    }

    public static function hasData(string $key)
    {
        return isset(Coroutine::getContext()['data']) && array_key_exists($key, Coroutine::getContext()['data']);
    }

    public static function rememberData(string $key, $value)
    {
        if (self::hasData($key)) {
            return self::getData($key);
        }

        if ($value instanceof Closure) {
            // 获取缓存数据
            $value = $value();
        }

        self::setData($key, $value);

        return $value;
    }

    public static function setData(string $key, $value)
    {
        Coroutine::getContext()['data'][$key] = $value;
    }

    public static function removeData(string $key)
    {
        unset(Coroutine::getContext()['data'][$key]);
    }

    public static function getDataKeys()
    {
        return array_keys(Coroutine::getContext()['data'] ?? []);
    }

    public static function clear()
    {
        if (isset(Coroutine::getContext()['data'])) {
            unset(Coroutine::getContext()['data']);
        }
    }

    public static function getCoroutineId()
    {
        return Coroutine::getuid();
    }
}
