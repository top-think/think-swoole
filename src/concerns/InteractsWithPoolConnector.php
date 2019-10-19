<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Channel;

trait InteractsWithPoolConnector
{
    protected $handler;

    protected $pool;

    protected $release = true;

    public function __construct($handler, Channel $pool)
    {
        $this->handler = $handler;
        $this->pool    = $pool;
    }

    public function __call($method, $arguments)
    {
        return $this->handler->{$method}(...$arguments);
    }

    public function release()
    {
        if (!$this->release) {
            return;
        }
        $this->release = false;

        if (!$this->pool->isFull()) {
            $this->pool->push($this->handler, 0.001);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
