<?php

namespace think\swoole\coroutine;

use ArrayObject;
use Closure;
use Swoole\Coroutine;

class Context
{

    /**
     * 获取协程上下文
     * @param null $cid
     * @return Coroutine\Context
     */
    public static function get($cid = null)
    {
        return Coroutine::getContext($cid);
    }

    public static function getDataObject()
    {
        $context = self::get();
        if (!isset($context['data'])) {
            $context['data'] = new ArrayObject();
        }
        return $context['data'];
    }

    /**
     * 获取当前协程临时数据
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public static function getData(string $key, $default = null)
    {
        return self::getDataObject()[$key] ?? $default;
    }

    /**
     * 判断是否存在临时数据
     * @param string $key
     * @return bool
     */
    public static function hasData(string $key)
    {
        return array_key_exists($key, self::getDataObject());
    }

    /**
     * 写入临时数据
     * @param string $key
     * @param $value
     */
    public static function setData(string $key, $value)
    {
        self::getDataObject()[$key] = $value;
    }

    /**
     * 如果不存在则写入数据
     * @param string $key
     * @param $value
     * @return mixed|null
     */
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
     * 删除数据
     * @param string $key
     */
    public static function removeData(string $key)
    {
        unset(self::getDataObject()[$key]);
    }

    /**
     * @internal
     * 清空数据
     */
    public static function clear()
    {
        self::getDataObject()->exchangeArray([]);
    }

    /**
     * 获取当前协程ID
     * @return mixed
     */
    public static function getCoroutineId()
    {
        return Coroutine::getuid();
    }
}
