<?php

namespace think\swoole\coroutine;

use Closure;
use Swoole\Coroutine;

class Context
{

    /**
     * The data in different coroutine environment.
     *
     * @var array
     */
    protected static $data = [];

    /**
     * Get data by current coroutine id.
     *
     * @param string $key
     *
     * @param null   $default
     * @return mixed|null
     */
    public static function getData(string $key, $default = null)
    {
        return static::$data[static::getCoroutineId()][$key] ?? $default;
    }

    public static function hasData(string $key)
    {
        return isset(static::$data[static::getCoroutineId()]) && array_key_exists($key, static::$data[static::getCoroutineId()]);
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

    /**
     * Set data by current coroutine id.
     *
     * @param string $key
     * @param        $value
     */
    public static function setData(string $key, $value)
    {
        static::$data[static::getCoroutineId()][$key] = $value;
    }

    /**
     * Remove data by current coroutine id.
     *
     * @param string $key
     */
    public static function removeData(string $key)
    {
        unset(static::$data[static::getCoroutineId()][$key]);
    }

    /**
     * Get data keys by current coroutine id.
     */
    public static function getDataKeys()
    {
        return array_keys(static::$data[static::getCoroutineId()] ?? []);
    }

    /**
     * Clear data by current coroutine id.
     */
    public static function clear()
    {
        unset(static::$data[static::getCoroutineId()]);
    }

    /**
     * Get current coroutine id.
     */
    public static function getCoroutineId()
    {
        return Coroutine::getuid();
    }
}
