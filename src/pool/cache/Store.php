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
        return $this->handler->has($name);
    }

    /**
     * @inheritDoc
     */
    public function get($name, $default = null)
    {
        return $this->handler->get($name, $default);
    }

    /**
     * @inheritDoc
     */
    public function set($name, $value, $expire = null)
    {
        return $this->handler->set($name, $value, $expire);
    }

    /**
     * @inheritDoc
     */
    public function inc(string $name, int $step = 1)
    {
        return $this->handler->inc($name, $step);
    }

    /**
     * @inheritDoc
     */
    public function dec(string $name, int $step = 1)
    {
        return $this->handler->dec($name, $step);
    }

    /**
     * @inheritDoc
     */
    public function delete($name)
    {
        return $this->handler->delete($name);
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->handler->clear();
    }

    /**
     * @inheritDoc
     */
    public function clearTag(array $keys)
    {
        return $this->handler->clearTag($keys);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        return $this->handler->getMultiple($keys, $default);
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        return $this->handler->setMultiple($values, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        return $this->handler->deleteMultiple($keys);
    }
}
