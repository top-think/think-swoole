<?php

namespace think\swoole\lock;

use InvalidArgumentException;
use Redis as PHPRedis;
use Swoole\Coroutine;
use think\swoole\contract\LockInterface;
use think\swoole\Manager;
use think\swoole\redis\Connection;

class Redis implements LockInterface
{
    use OwnerToken;

    protected const ACQUIRE_SCRIPT = <<<'LUA'
if redis.call('SET', KEYS[1], ARGV[1], 'NX', 'EX', ARGV[2]) then
    return 1
end

return 0
LUA;

    protected const RELEASE_SCRIPT = <<<'LUA'
if redis.call('GET', KEYS[1]) ~= ARGV[1] then
    return 0
end

redis.call('DEL', KEYS[1])
return 1
LUA;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Connection|null
     */
    protected $connection;

    public function __construct(array $config, Manager $manager)
    {
        $this->config  = $config;
        $this->manager = $manager;
    }

    public function prepare()
    {
    }

    public function lock($name, $expire = 60)
    {
        $expire = (int) $expire;
        if ($expire < 1) {
            throw new InvalidArgumentException('Lock expiration must be a positive integer');
        }

        $key   = $this->getKey($name);
        $token = $this->createOwnerToken();

        while (!$this->acquire($key, $token, $expire)) {
            $this->wait();
        }

        $this->saveOwnerToken($name, $token);

        return true;
    }

    public function unlock($name)
    {
        $token = $this->getOwnerToken($name);

        if ($token === null) {
            return false;
        }

        $released = $this->release($this->getKey($name), $token);
        if ($released) {
            $this->removeOwnerToken($name);
        }

        return $released;
    }

    protected function acquire(string $key, string $token, int $expire): bool
    {
        return $this->getConnection()->run(function (PHPRedis $redis) use ($key, $token, $expire) {
            return (int) $redis->eval(self::ACQUIRE_SCRIPT, [$key, $token, $expire], 1) === 1;
        });
    }

    protected function release(string $key, string $token): bool
    {
        return $this->getConnection()->run(function (PHPRedis $redis) use ($key, $token) {
            return (int) $redis->eval(self::RELEASE_SCRIPT, [$key, $token], 1) === 1;
        });
    }

    protected function wait(): void
    {
        Coroutine::sleep(self::RETRY_INTERVAL);
    }

    protected function getConnection(): Connection
    {
        if (!$this->connection instanceof Connection) {
            $this->connection = $this->manager->getRedis()->connection(
                (string) ($this->config['connection'] ?? 'default')
            );
        }

        return $this->connection;
    }

    protected function getKey($name): string
    {
        return (string) ($this->config['prefix'] ?? 'swoole:lock:') . $name;
    }

}
