<?php

namespace think\swoole\websocket\room;

use InvalidArgumentException;
use Redis as PHPRedis;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use Swoole\Timer;
use think\helper\Arr;
use think\swoole\contract\websocket\RoomInterface;
use think\swoole\Manager;
use think\swoole\Pool;

/**
 * Class RedisRoom
 */
class Redis implements RoomInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $prefix = 'swoole:room:';

    /** @var Manager */
    protected $manager;

    /** @var ConnectionPool */
    protected $pool;

    /**
     * RedisRoom constructor.
     *
     * @param Manager $manager
     * @param array $config
     */
    public function __construct(Manager $manager, array $config)
    {
        $this->manager = $manager;
        $this->config  = $config;

        if ($prefix = Arr::get($this->config, 'prefix')) {
            $this->prefix = $prefix;
        }
    }

    /**
     * @return RoomInterface
     */
    public function prepare(): RoomInterface
    {
        $this->initData();
        $this->prepareRedis();
        $this->addHeartbeatWorker();
        return $this;
    }

    protected function prepareRedis()
    {
        $this->manager->onEvent('workerStart', function () {
            $config     = $this->config;
            $this->pool = new ConnectionPool(
                Pool::pullPoolConfig($config),
                new PhpRedisConnector(),
                $config
            );
            $this->manager->getPools()->add('websocket.room', $this->pool);
        });
    }

    protected function initData()
    {
        // prepare 阶段 pool 尚未初始化，使用独立连接清理当前节点旧数据
        $redis = $this->createRedisConnection();
        try {
            $this->clearByFdPrefix($redis, $this->manager->getNodeId() . '.');
        } finally {
            (new PhpRedisConnector())->disconnect($redis);
        }
    }

    /**
     * Add multiple socket fds to a room.
     *
     * @param string $fd
     * @param array|string $roomNames
     */
    public function add($fd, $roomNames)
    {
        $rooms = is_array($roomNames) ? $roomNames : [$roomNames];

        $this->addValue($fd, $rooms, RoomInterface::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->addValue($room, [$fd], RoomInterface::ROOMS_KEY);
        }
    }

    /**
     * Delete multiple socket fds from a room.
     *
     * @param string $fd
     * @param array|string $rooms
     */
    public function delete($fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        $rooms = empty($rooms) ? $this->getRooms($fd) : $rooms;

        $this->removeValue($fd, $rooms, RoomInterface::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->removeValue($room, [$fd], RoomInterface::ROOMS_KEY);
        }
    }

    protected function runWithRedis(\Closure $callable)
    {
        $redis = $this->pool->borrow();
        try {
            return $callable($redis);
        } finally {
            $this->pool->return($redis);
        }
    }

    /**
     * 创建独立 Redis 连接（不经过连接池）
     */
    protected function createRedisConnection(): PHPRedis
    {
        return (new PhpRedisConnector())->connect($this->config);
    }

    /**
     * Add value to redis.
     *
     * @param        $key
     * @param array $values
     * @param string $table
     *
     * @return $this
     */
    protected function addValue($key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $this->runWithRedis(function (PHPRedis $redis) use ($redisKey, $values) {
            $pipe = $redis->multi(PHPRedis::PIPELINE);

            foreach ($values as $value) {
                $pipe->sadd($redisKey, $value);
            }

            $pipe->exec();
        });

        return $this;
    }

    /**
     * Remove value from redis.
     *
     * @param        $key
     * @param array $values
     * @param string $table
     *
     * @return $this
     */
    protected function removeValue($key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $this->runWithRedis(function (PHPRedis $redis) use ($redisKey, $values) {
            $pipe = $redis->multi(PHPRedis::PIPELINE);
            foreach ($values as $value) {
                $pipe->srem($redisKey, $value);
            }
            $pipe->exec();
        });

        return $this;
    }

    /**
     * Get all sockets by a room key.
     *
     * @param string $room
     *
     * @return array
     */
    public function getClients(string $room)
    {
        return $this->getValue($room, RoomInterface::ROOMS_KEY) ?: [];
    }

    /**
     * Get all rooms by a fd.
     *
     * @param string $fd
     *
     * @return array
     */
    public function getRooms($fd)
    {
        return $this->getValue($fd, RoomInterface::DESCRIPTORS_KEY) ?: [];
    }

    /**
     * Check table for rooms and descriptors.
     *
     * @param string $table
     */
    protected function checkTable(string $table)
    {
        if (!in_array($table, [RoomInterface::ROOMS_KEY, RoomInterface::DESCRIPTORS_KEY])) {
            throw new InvalidArgumentException("Invalid table name: `{$table}`.");
        }
    }

    /**
     * Get value.
     *
     * @param string $key
     * @param string $table
     *
     * @return array
     */
    protected function getValue(string $key, string $table)
    {
        $this->checkTable($table);

        return $this->runWithRedis(function (PHPRedis $redis) use ($table, $key) {
            return $redis->smembers($this->getKey($key, $table));
        });
    }

    /**
     * Get key.
     *
     * @param string $key
     * @param string $table
     *
     * @return string
     */
    protected function getKey(string $key, string $table)
    {
        return "{$this->prefix}{$table}:{$key}";
    }

    /**
     * Clear rooms and clients by node/worker prefix.
     */
    public function clear(?string $nodeId = null, ?int $workerId = null)
    {
        if ($nodeId === null) {
            // 全量清理
            $this->runWithRedis(function (PHPRedis $redis) {
                $keys = $redis->keys("{$this->prefix}*");
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            });
            return;
        }

        $fdPrefix = $workerId !== null
            ? "{$nodeId}.{$workerId}."
            : "{$nodeId}.";

        $this->runWithRedis(function (PHPRedis $redis) use ($fdPrefix) {
            $this->clearByFdPrefix($redis, $fdPrefix);
        });
    }

    /**
     * 按 fd 前缀清理房间数据（先收集再 pipeline 执行）
     */
    protected function clearByFdPrefix(PHPRedis $redis, string $fdPrefix): void
    {
        $allKeys = $redis->keys("{$this->prefix}*");

        $fdsToRemove = [];
        $keysToDelete = [];

        foreach ($allKeys as $key) {
            $suffix = substr($key, strlen($this->prefix));

            if (str_starts_with($suffix, 'rooms:')) {
                $fds = $redis->smembers($key);
                foreach ($fds as $fd) {
                    if (str_starts_with($fd, $fdPrefix)) {
                        $fdsToRemove[$key][] = $fd;
                    }
                }
            } elseif (str_starts_with($suffix, 'fds:')) {
                $fd = substr($suffix, 4);
                if (str_starts_with($fd, $fdPrefix)) {
                    $keysToDelete[] = $key;
                }
            }
        }

        $pipe = $redis->multi(PHPRedis::PIPELINE);
        foreach ($fdsToRemove as $roomKey => $fds) {
            foreach ($fds as $fd) {
                $pipe->srem($roomKey, $fd);
            }
        }
        foreach ($keysToDelete as $key) {
            $pipe->del($key);
        }
        $pipe->exec();
    }

    /**
     * 心跳 Worker：维护节点心跳 + 清理死节点房间数据
     */
    protected function addHeartbeatWorker(): void
    {
        $this->manager->addWorker(function () {
            $nodeId = $this->manager->getNodeId();
            $heartbeatKey = "{$this->prefix}heartbeat:{$nodeId}";
            $nodesKey = "{$this->prefix}nodes";
            $interval = 5;
            $ttl = 15;

            // 独立 Redis 连接用于心跳操作（长连接，不断开）
            $redis = $this->createRedisConnection();

            // 注册本节点
            $redis->setex($heartbeatKey, $ttl, 1);
            $redis->sadd($nodesKey, $nodeId);

            Timer::tick($interval * 1000, function () use ($redis, $nodeId, $heartbeatKey, $ttl, $nodesKey) {
                // 更新心跳
                $redis->setex($heartbeatKey, $ttl, 1);

                // 检查死节点并清理
                $knownNodes = $redis->smembers($nodesKey);
                foreach ($knownNodes as $knownNodeId) {
                    if ($knownNodeId === $nodeId) {
                        continue;
                    }
                    if (!$redis->exists("{$this->prefix}heartbeat:{$knownNodeId}")) {
                        // 只有 srem 成功的节点才执行清理，避免多节点重复操作
                        if ($redis->srem($nodesKey, $knownNodeId) > 0) {
                            $this->clearByFdPrefix($redis, $knownNodeId . '.');
                        }
                    }
                }
            });
        }, 'websocket heartbeat');
    }

}