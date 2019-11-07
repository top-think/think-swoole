<?php

namespace think\swoole;

use Smf\ConnectionPool\ConnectionPool;
use think\helper\Arr;

class Pool
{
    protected $pools = [];

    /**
     * @param string         $name
     * @param ConnectionPool $pool
     *
     * @return Pool
     */
    public function add(string $name, ConnectionPool $pool)
    {
        $pool->init();
        $this->pools[$name] = $pool;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return ConnectionPool
     */
    public function get(string $name)
    {
        return $this->pools[$name] ?? null;
    }

    public function close(string $key)
    {
        return $this->pools[$key]->close();
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->pools;
    }

    public function closeAll()
    {
        foreach ($this->pools as $pool) {
            $pool->close();
        }
    }

    /**
     * @param string $key
     *
     * @return ConnectionPool
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    public static function pullPoolConfig(&$config)
    {
        return [
            'minActive'         => Arr::pull($config, 'min_active', 0),
            'maxActive'         => Arr::pull($config, 'max_active', 10),
            'maxWaitTime'       => Arr::pull($config, 'max_wait_time', 5),
            'maxIdleTime'       => Arr::pull($config, 'max_idle_time', 20),
            'idleCheckInterval' => Arr::pull($config, 'idle_check_interval', 10),
        ];
    }
}
