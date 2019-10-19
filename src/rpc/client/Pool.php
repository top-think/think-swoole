<?php

namespace think\swoole\rpc\client;

use Swoole\Coroutine\Channel;
use think\helper\Arr;
use think\swoole\concerns\InteractsWithPool;

class Pool
{
    use InteractsWithPool;

    protected $clients;

    public function __construct($clients)
    {
        $this->clients = $clients;
    }

    protected function getPoolMaxActive($name)
    {
        return $this->getClientConfig($name, 'max_active', 3);
    }

    protected function getPoolMaxWaitTime($name)
    {
        return $this->getClientConfig($name, 'max_wait_time', 3);
    }

    public function getClientConfig($client, $name, $default = null)
    {
        return Arr::get($this->clients, $client . "." . $name, $default);
    }

    /**
     * @param $name
     * @return Connection
     */
    public function connect($name)
    {
        return $this->getPoolConnection($name);
    }

    protected function buildPoolConnection($client, Channel $pool)
    {
        return new Connection($client, $pool);
    }

    protected function createPoolConnection(string $name)
    {
        $host    = $this->getClientConfig($name, 'host', '127.0.0.1');
        $port    = $this->getClientConfig($name, 'port', 9000);
        $timeout = $this->getClientConfig($name, 'timeout', 0.5);

        return new Client($host, $port, $timeout);
    }
}
