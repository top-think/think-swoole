<?php

namespace think\swoole\pool;

use Smf\ConnectionPool\Connectors\ConnectorInterface;

class Connector implements ConnectorInterface
{
    protected $connector;
    protected $checker;

    public function __construct($connector)
    {
        $this->connector = $connector;
    }

    public function setChecker($checker)
    {
        $this->checker = $checker;
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
        if ($this->checker) {
            return call_user_func($this->checker, $connection);
        }
        return true;
    }

    public function reset($connection, array $config)
    {

    }

    public function validate($connection): bool
    {
        return true;
    }
}
