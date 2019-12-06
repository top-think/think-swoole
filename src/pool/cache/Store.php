<?php

namespace think\swoole\pool\cache;

use Psr\SimpleCache\CacheInterface;
use think\contract\CacheHandlerInterface;
use think\swoole\concerns\InteractsWithPoolConnector;

/**
 * Class Store
 * @package think\swoole\pool\cache
 *
 * @property CacheHandlerInterface|CacheInterface $handler
 */
class Store implements CacheHandlerInterface, CacheInterface
{
    use InteractsWithPoolConnector;

    /**
     * @inheritDoc
     */
    public function has($name)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function get($name, $default = null)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function set($name, $value, $expire = null)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function inc(string $name, int $step = 1)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function dec(string $name, int $step = 1)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function delete($name)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function clearTag(array $keys)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }
}
