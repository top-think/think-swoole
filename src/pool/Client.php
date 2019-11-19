<?php

namespace think\swoole\pool;

use RuntimeException;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use think\helper\Arr;

class Client implements ConnectorInterface
{

    /**
     * Connect to the specified Server and returns the connection resource
     * @param array $config
     * @return \Swoole\Coroutine\Client
     */
    public function connect(array $config)
    {
        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

        $host    = Arr::pull($config, 'host');
        $port    = Arr::pull($config, 'port');
        $timeout = Arr::pull($config, 'timeout');

        $client->set($config);

        if (!$client->connect($host, $port, $timeout)) {
            throw new RuntimeException(
                sprintf('Connect failed host=%s port=%d', $host, $port)
            );
        }

        return $client;
    }

    /**
     * Disconnect and free resources
     * @param \Swoole\Coroutine\Client $connection
     * @return mixed
     */
    public function disconnect($connection)
    {
        $connection->close();
    }

    /**
     * Whether the connection is established
     * @param \Swoole\Coroutine\Client $connection
     * @return bool
     */
    public function isConnected($connection): bool
    {
        return $connection->isConnected();
    }

    /**
     * Reset the connection
     * @param \Swoole\Coroutine\Client $connection
     * @param array                    $config
     * @return mixed
     */
    public function reset($connection, array $config)
    {

    }

    /**
     * Validate the connection
     *
     * @param \Swoole\Coroutine\Client $connection
     * @return bool
     */
    public function validate($connection): bool
    {
        return $connection instanceof \Swoole\Coroutine\Client;
    }
}
