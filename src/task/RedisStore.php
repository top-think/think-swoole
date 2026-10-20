<?php

namespace think\swoole\task;

use Redis as PHPRedis;
use think\swoole\contract\TaskStoreInterface;
use think\swoole\redis\Connection;

class RedisStore implements TaskStoreInterface
{
    protected const START_SCRIPT = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 1 then
    return 0
end

redis.call('SET', KEYS[1], ARGV[1], 'EX', ARGV[2])
return 1
LUA;

    protected const HEARTBEAT_SCRIPT = <<<'LUA'
if redis.call('GET', KEYS[1]) ~= ARGV[1] then
    return 0
end

redis.call('EXPIRE', KEYS[1], ARGV[2])
return 1
LUA;

    protected const STOP_SCRIPT = <<<'LUA'
if redis.call('GET', KEYS[1]) ~= ARGV[1] then
    return 0
end

redis.call('DEL', KEYS[1])
return 1
LUA;

    /**
     * @var Connection
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix;

    public function __construct(Connection $redis, string $prefix = 'swoole:task:')
    {
        $this->redis  = $redis;
        $this->prefix = $prefix;
    }

    public function start(string $id, string $owner, int $leaseTtl): bool
    {
        return $this->runWithRedis(function (PHPRedis $redis) use ($id, $owner, $leaseTtl) {
            $result = $redis->eval(self::START_SCRIPT, [
                $this->getKey($id),
                $owner,
                $leaseTtl,
            ], 1);

            return (int) $result === 1;
        });
    }

    public function heartbeat(string $id, string $owner, int $leaseTtl): bool
    {
        return $this->runWithRedis(function (PHPRedis $redis) use ($id, $owner, $leaseTtl) {
            $result = $redis->eval(self::HEARTBEAT_SCRIPT, [
                $this->getKey($id),
                $owner,
                $leaseTtl,
            ], 1);

            return (int) $result === 1;
        });
    }

    public function stop(string $id, string $owner): bool
    {
        return $this->runWithRedis(function (PHPRedis $redis) use ($id, $owner) {
            $result = $redis->eval(self::STOP_SCRIPT, [
                $this->getKey($id),
                $owner,
            ], 1);

            return (int) $result === 1;
        });
    }

    public function exists(string $id): bool
    {
        return $this->runWithRedis(function (PHPRedis $redis) use ($id) {
            return (int) $redis->exists($this->getKey($id)) > 0;
        });
    }

    protected function getKey(string $id): string
    {
        return $this->prefix . $id;
    }

    protected function runWithRedis(callable $callable)
    {
        return $this->redis->run($callable);
    }
}
