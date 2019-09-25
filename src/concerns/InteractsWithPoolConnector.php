<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Channel;

trait InteractsWithPoolConnector
{
    protected $handler;

    protected $pool;

    protected $return = true;

    public function __construct($handler, Channel $pool, $return = true)
    {
        $this->handler = $handler;
        $this->pool    = $pool;
        $this->return  = $return;
    }

    public function returnToPool(): bool
    {
        if (!$this->return) {
            return true;
        }

        if ($this->pool->isFull()) {
            return false;
        }

        return $this->pool->push($this->handler, 0.001);
    }

    public function __call($method, $arguments)
    {
        return $this->handler->{$method}(...$arguments);
    }

    public function __destruct()
    {
        $this->returnToPool();
    }
}
