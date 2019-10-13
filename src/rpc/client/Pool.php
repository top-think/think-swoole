<?php

namespace think\swoole\rpc\client;

use think\helper\Arr;
use think\swoole\concerns\InteractsWithPool;
use think\swoole\coroutine\Context;

class Pool
{
    use InteractsWithPool;

    protected $clients;

    public function __construct($clients)
    {
        $this->clients = $clients;
    }

    protected function getMaxActive($name)
    {
        return $this->getClientConfig($name, 'max_active', 3);
    }

    protected function getMaxWaitTime($name)
    {
        return $this->getClientConfig($name, 'max_wait_time', 3);
    }

    protected function getClientConfig($client, $name, $default = null)
    {
        return Arr::get($this->clients, $client . "." . $name, $default);
    }

    protected function createClient($name)
    {
        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

        $host    = $this->getClientConfig($name, 'host', '127.0.0.1');
        $port    = $this->getClientConfig($name, 'port', 9000);
        $timeout = $this->getClientConfig($name, 'timeout', 0.5);

        if (!$client->connect($host, $port, $timeout)) {
            throw new \Exception(
                sprintf('Connect failed host=%s port=%d', $host, $port)
            );
        }

        return $client;
    }

    /**
     * @param $name
     * @return Client
     */
    public function client($name)
    {
        return Context::rememberData("rpc.client.{$name}", function () use ($name) {

            $pool = $this->getPool($name);

            if (!isset($this->connectionCount[$name])) {
                $this->connectionCount[$name] = 0;
            }

            if ($this->connectionCount[$name] < $this->getMaxActive($name)) {
                //新建
                $this->connectionCount[$name]++;
                return new Client($this->createClient($name), $pool);
            }

            $client = $pool->pop($this->getMaxWaitTime($name));

            if ($client === false) {
                throw new \RuntimeException(sprintf(
                    'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                    $this->getMaxWaitTime($name),
                    $pool->length(),
                    $this->connectionCount[$name] ?? 0
                ));
            }

            return new Client($client, $pool);
        });
    }
}
