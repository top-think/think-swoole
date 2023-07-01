<?php

namespace think\swoole\pool\proxy;

use Psr\SimpleCache\CacheInterface;
use think\contract\CacheHandlerInterface;
use think\swoole\pool\Proxy;

class Store extends Proxy implements CacheHandlerInterface, CacheInterface
{
    /**
     * @inheritDoc
     */
    public function has($name): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function get($name, $default = null): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function set($name, $value, $expire = null): bool
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
    public function delete($name): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
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
    public function getMultiple($keys, $default = null): iterable
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function tag($name)
    {
        return $this->__call(__FUNCTION__, func_get_args());
    }
}
