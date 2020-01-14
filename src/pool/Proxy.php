<?php

namespace think\swoole\pool;

use RuntimeException;
use Swoole\Coroutine\Channel;

abstract class Proxy
{
    protected $handler;

    protected $pool;

    protected $released = false;

    public function __construct($handler, Channel $pool)
    {
        $this->handler = $handler;
        $this->pool    = $pool;
    }

    public function __call($method, $arguments)
    {
        if ($this->released) {
            throw new RuntimeException("Connection already has been released!");
        }

        return $this->handler->{$method}(...$arguments);
    }

    public function release()
    {
        if ($this->released) {
            return;
        }
        $this->released = true;

        if (!$this->pool->isFull()) {
            $this->pool->push($this->handler, 0.001);
        }
    }

    public function __destruct()
    {
        go(function () {
            $this->release();
        });
    }
}
