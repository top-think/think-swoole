<?php

namespace think\swoole\pool;

use Closure;
use RuntimeException;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Coroutine;
use Swoole\Event;
use think\swoole\coroutine\Context;
use think\swoole\Pool;

abstract class Proxy
{
    const KEY_RELEASED = '__released';

    protected $pool;

    /**
     * Proxy constructor.
     * @param Closure $creator
     * @param array $config
     */
    public function __construct($creator, $config)
    {
        $this->pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            new class($creator) implements ConnectorInterface {

                protected $creator;

                public function __construct($creator)
                {
                    $this->creator = $creator;
                }

                public function connect(array $config)
                {
                    return call_user_func($this->creator);
                }

                public function disconnect($connection)
                {
                    //强制回收内存，完成连接释放
                    Event::defer(function () {
                        Coroutine::create('gc_collect_cycles');
                    });
                }

                public function isConnected($connection): bool
                {
                    return true;
                }

                public function reset($connection, array $config)
                {

                }

                public function validate($connection): bool
                {
                    return true;
                }
            },
            []
        );

        $this->pool->init();
    }

    protected function getPoolConnection()
    {
        return Context::rememberData('connection.' . spl_object_id($this), function () {
            $connection = $this->pool->borrow();

            $connection->{static::KEY_RELEASED} = false;

            Coroutine::defer(function () use ($connection) {
                //自动归还
                $connection->{static::KEY_RELEASED} = true;
                $this->pool->return($connection);
            });

            return $connection;
        });
    }

    public function release()
    {
        $connection = $this->getPoolConnection();
        if ($connection->{static::KEY_RELEASED}) {
            return;
        }
        $this->pool->return($connection);
    }

    public function __call($method, $arguments)
    {
        $connection = $this->getPoolConnection();
        if ($connection->{static::KEY_RELEASED}) {
            throw new RuntimeException('Connection already has been released!');
        }

        return $connection->{$method}(...$arguments);
    }

}
