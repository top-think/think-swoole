<?php

namespace think\swoole\coroutine;

use ArrayObject;
use Closure;
use Swoole\Coroutine;

class Context
{

    /**
     * 获取协程上下文
     * @param int $cid
     * @return Coroutine\Context
     */
    public static function get($cid = 0)
    {
        return Coroutine::getContext($cid);
    }

    public static function getDataObject()
    {
        $context = self::get();
        if (!isset($context['#data'])) {
            $context['#data'] = new ArrayObject();
        }
        return $context['#data'];
    }

    /**
     * 获取当前协程临时数据
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public static function getData(string $key, $default = null)
    {
        if (self::hasData($key)) {
            return self::getDataObject()->offsetGet($key);
        }
        return $default;
    }

    /**
     * 判断是否存在临时数据
     * @param string $key
     * @return bool
     */
    public static function hasData(string $key)
    {
        return self::getDataObject()->offsetExists($key);
    }

    /**
     * 写入临时数据
     * @param string $key
     * @param $value
     */
    public static function setData(string $key, $value)
    {
        self::getDataObject()->offsetSet($key, $value);
    }

    /**
     * 删除数据
     * @param string $key
     */
    public static function removeData(string $key)
    {
        if (self::hasData($key)) {
            self::getDataObject()->offsetUnset($key);
        }
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
     * @deprecated
     */
    public static function getCoroutineId()
    {
        return Coroutine::getCid();
    }

    /**
     * 获取当前协程ID
     * @return mixed
     */
    public static function getId()
    {
        return Coroutine::getCid();
    }

    /**
     * 获取父级协程ID
     * @param int $id
     * @return int
     */
    public static function getPid($id = 0)
    {
        $context = self::get($id);

        if ($context && $context->offsetExists('#pid')) {
            return (int) $context->offsetGet('#pid');
        }

        $pid = Coroutine::getPcid($id);

        return $pid === false ? -1 : (int) $pid;
    }

    /**
     * 绑定父级协程ID
     * @param $id
     */
    public static function attach($id)
    {
        self::get()->offsetSet('#pid', $id);
    }

    /**
     * 绑定根协程ID
     * @param int $id
     */
    public static function setRootId($id)
    {
        if ($context = self::get()) {
            $context->offsetSet('#root-id', $id);
        }
    }

    /**
     * 获取根协程ID
     * @param bool $init
     * @return int
     */
    public static function getRootId($init = false)
    {
        $context = self::get();

        //非协程环境
        if (!$context) {
            return -1;
        }

        if ($init) {
            $context->offsetSet('#root', true);
            $context->offsetUnset('#root-id');
            return self::getId();
        }

        if ($context->offsetExists('#root')) {
            return self::getId();
        }

        //创建协程时继承下来的，或之前查找过并缓存的根协程ID
        if ($context->offsetExists('#root-id')) {
            return (int) $context->offsetGet('#root-id');
        }

        $cid = self::getId();
        while (($pid = self::getPid($cid)) > 0) {
            $parent = self::get($pid);

            //祖先协程已退出，无法继续向上查找
            if (!$parent) {
                break;
            }

            if ($parent->offsetExists('#root')) {
                //缓存查找结果，后续调用与后代协程不再向上查找
                $context->offsetSet('#root-id', $pid);
                return $pid;
            }

            $cid = $pid;
        }

        return -1;
    }

    /**
     * 获取当前协程所属的作用域
     *
     * 作用域只保存在 root 协程上，根据根协程ID直接读取，
     * 因此凡是能定位到 root 的协程都能拿到作用域。
     *
     * @param int|null $rootId 已解析的根协程ID，未传时自动解析
     * @return Scope|null
     */
    public static function getScope($rootId = null)
    {
        if ($rootId === null) {
            $rootId = self::getRootId();
        }

        if ($rootId > 0) {
            $root = self::get($rootId);

            if ($root && $root->offsetExists('#scope')) {
                return $root->offsetGet('#scope');
            }
        }

        return null;
    }

    /**
     * 绑定作用域（仅 root 协程需要）
     * @param Scope $scope
     */
    public static function setScope(Scope $scope)
    {
        if ($context = self::get()) {
            $context->offsetSet('#scope', $scope);
        }
    }
}
