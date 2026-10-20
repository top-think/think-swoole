<?php

namespace think\swoole\redis;

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
            return $callback($redis);
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
            return $callback($redis);
        } finally {
            $this->connector->disconnect($redis);
        }
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
