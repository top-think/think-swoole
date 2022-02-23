<?php

namespace think\swoole\pool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;

class Connector implements ConnectorInterface
{
    protected $connector;

    public function __construct($connector)
    {
        $this->connector = $connector;
    }

    public function connect(array $config)
    {
        return call_user_func($this->connector, $config);
    }

    public function disconnect($connection)
    {

    }

    public function isConnected($connection): bool
    {
        return !property_exists($connection, Proxy::KEY_DISCONNECTED);
    }

    public function reset($connection, array $config)
    {

    }

    public function validate($connection): bool
    {
        return true;
    }
}
