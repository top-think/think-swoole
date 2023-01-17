<?php

namespace think\swoole\pool;

use Closure;
use Exception;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine;
use think\swoole\coroutine\Context;
use think\swoole\Pool;
use Throwable;

#[\AllowDynamicProperties]
abstract class Proxy
{
    const KEY_RELEASED = '__released';

    const KEY_DISCONNECTED = '__disconnected';

    protected $pool;

    /**
     * Proxy constructor.
     * @param Closure|ConnectorInterface $connector
     * @param array                      $config
     */
    public function __construct($connector, $config, array $connectionConfig = [])
    {
        if ($connector instanceof Closure) {
            $connector = new Connector($connector);
        }

        $this->pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            $connector,
            $connectionConfig
        );

        $this->pool->init();
    }

    protected function getPoolConnection()
    {
        return Context::rememberData('connection.' . spl_object_id($this), function () {
            $connection = $this->pool->borrow();

            $connection->{static::KEY_RELEASED} = false;

            Coroutine::defer(function () use ($connection) {
                //自动释放
                $this->releaseConnection($connection);
            });

            return $connection;
        });
    }

    protected function releaseConnection($connection)
    {
        if ($connection->{static::KEY_RELEASED}) {
            return;
        }
        $connection->{static::KEY_RELEASED} = true;
        $this->pool->return($connection);
    }

    public function release()
    {
        $connection = $this->getPoolConnection();
        $this->releaseConnection($connection);
    }

    public function __call($method, $arguments)
    {
        $connection = $this->getPoolConnection();
        if ($connection->{static::KEY_RELEASED}) {
            throw new RuntimeException('Connection already has been released!');
        }

        try {
            return $connection->{$method}(...$arguments);
        } catch (Exception|Throwable $e) {
            $connection->{static::KEY_DISCONNECTED} = true;
            throw $e;
        }
    }

}
