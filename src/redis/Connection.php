<?php

namespace think\swoole\redis;

use Generator;
use LogicException;
use Redis as PHPRedis;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use think\swoole\Pool;

/**
 * @mixin PHPRedis
 */
class Connection
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var ConnectorInterface
     */
    protected $connector;

    /**
     * @var ConnectionPool|null
     */
    protected $pool;

    public function __construct(string $name, array $config, ?ConnectorInterface $connector = null)
    {
        $this->name      = $name;
        $this->config    = $config;
        $this->connector = $connector ?? new PhpRedisConnector();
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 从连接池借用一个 Redis 客户端
     */
    public function run(callable $callback)
    {
        $pool  = $this->getPool();
        $redis = $pool->borrow();

        try {
            return $this->ensureSynchronousResult($callback($redis), 'run');
        } finally {
            $pool->return($redis);
        }
    }

    /**
     * 使用不进入连接池的独占连接
     */
    public function dedicated(callable $callback)
    {
        $redis = $this->connector->connect($this->config);

        try {
            return $this->ensureSynchronousResult($callback($redis), 'dedicated');
        } finally {
            $this->connector->disconnect($redis);
        }
    }

    /**
     * 使用独占连接执行流式回调，连接会保持到生成器结束
     */
    public function stream(callable $callback): Generator
    {
        return (function () use ($callback): Generator {
            $redis = $this->connector->connect($this->config);

            try {
                $result = $callback($redis);

                if (!$result instanceof Generator) {
                    throw new LogicException('Redis stream callback must return a Generator.');
                }

                yield from $result;
            } finally {
                $this->connector->disconnect($redis);
            }
        })();
    }

    public function __call($method, $arguments)
    {
        if (in_array(strtolower($method), ['subscribe', 'psubscribe', 'monitor', 'multi', 'pipeline'], true)) {
            throw new LogicException("Redis command [{$method}] must be executed through run() or dedicated()");
        }

        return $this->run(function (PHPRedis $redis) use ($method, $arguments) {
            return $redis->{$method}(...$arguments);
        });
    }

    protected function ensureSynchronousResult($result, string $method)
    {
        if ($result instanceof Generator) {
            throw new LogicException(
                "Redis callback [{$method}] must not return a Generator; use stream() for long-lived streams."
            );
        }

        return $result;
    }

    protected function getPool(): ConnectionPool
    {
        if (!$this->pool instanceof ConnectionPool) {
            $config = $this->config;
            $pool   = new ConnectionPool(
                Pool::pullPoolConfig($config),
                $this->connector,
                $config
            );
            $pool->init();
            $this->pool = $pool;
        }

        return $this->pool;
    }
}
