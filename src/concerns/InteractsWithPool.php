<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Channel;

trait InteractsWithPool
{

    /** @var Channel[] */
    protected $pools = [];

    protected $connectionCount = [];

    /**
     * 获取连接池
     * @param $name
     * @return Channel
     */
    protected function getPool($name)
    {
        if (empty($this->pools[$name])) {
            $this->pools[$name] = new Channel($this->getMaxActive());
        }
        return $this->pools[$name];
    }

    public function __destruct()
    {
        foreach ($this->pools as $pool) {
            while (true) {
                if ($pool->isEmpty()) {
                    break;
                }
                $handler = $pool->pop(0.001);
                unset($handler);
            }
            $pool->close();
        }
    }
}
