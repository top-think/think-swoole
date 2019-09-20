<?php

namespace think\swoole\websocket\room;

use InvalidArgumentException;
use Redis as PHPRedis;
use think\helper\Arr;
use think\swoole\contract\websocket\RoomInterface;

/**
 * Class RedisRoom
 */
class Redis implements RoomInterface
{
    /**
     * @var PHPRedis
     */
    protected $redis;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $prefix = 'swoole:';

    /**
     * RedisRoom constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        if ($prefix = Arr::get($this->config, 'prefix')) {
            $this->prefix = $prefix;
        }
    }

    /**
     * @return RoomInterface
     */
    public function prepare(): RoomInterface
    {
        $this->cleanRooms();

        //关闭redis
        $this->redis->close();
        $this->redis = null;
        return $this;
    }

    /**
     * Set redis client.
     *
     */
    protected function getRedis()
    {
        if (!$this->redis) {
            $host = Arr::get($this->config, 'host', '127.0.0.1');
            $port = Arr::get($this->config, 'port', 6379);

            $this->redis = new PHPRedis();
            $this->redis->pconnect($host, $port);
        }
        return $this->redis;
    }

    /**
     * Add multiple socket fds to a room.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function add(int $fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];

        $this->addValue($fd, $rooms, RoomInterface::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->addValue($room, [$fd], RoomInterface::ROOMS_KEY);
        }
    }

    /**
     * Delete multiple socket fds from a room.
     *
     * @param int fd
     * @param array|string rooms
     */
    public function delete(int $fd, $rooms)
    {
        $rooms = is_array($rooms) ? $rooms : [$rooms];
        $rooms = count($rooms) ? $rooms : $this->getRooms($fd);

        $this->removeValue($fd, $rooms, RoomInterface::DESCRIPTORS_KEY);

        foreach ($rooms as $room) {
            $this->removeValue($room, [$fd], RoomInterface::ROOMS_KEY);
        }
    }

    /**
     * Add value to redis.
     *
     * @param        $key
     * @param array  $values
     * @param string $table
     *
     * @return $this
     */
    protected function addValue($key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $pipe = $this->getRedis()->multi(PHPRedis::PIPELINE);

        foreach ($values as $value) {
            $pipe->sadd($redisKey, $value);
        }

        $pipe->exec();

        return $this;
    }

    /**
     * Remove value from reddis.
     *
     * @param        $key
     * @param array  $values
     * @param string $table
     *
     * @return $this
     */
    protected function removeValue($key, array $values, string $table)
    {
        $this->checkTable($table);
        $redisKey = $this->getKey($key, $table);

        $pipe = $this->getRedis()->multi(PHPRedis::PIPELINE);

        foreach ($values as $value) {
            $pipe->srem($redisKey, $value);
        }

        $pipe->exec();

        return $this;
    }

    /**
     * Get all sockets by a room key.
     *
     * @param string room
     *
     * @return array
     */
    public function getClients(string $room)
    {
        return $this->getValue($room, RoomInterface::ROOMS_KEY) ?? [];
    }

    /**
     * Get all rooms by a fd.
     *
     * @param int fd
     *
     * @return array
     */
    public function getRooms(int $fd)
    {
        return $this->getValue($fd, RoomInterface::DESCRIPTORS_KEY) ?? [];
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

        return $this->getRedis()->smembers($this->getKey($key, $table));
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
     * Clean all rooms.
     */
    protected function cleanRooms(): void
    {
        if (count($keys = $this->getRedis()->keys("{$this->prefix}*"))) {
            $this->getRedis()->del($keys);
        }
    }
}
