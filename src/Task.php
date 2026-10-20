<?php

namespace think\swoole;

use InvalidArgumentException;
use RuntimeException;
use Swoole\Timer;
use think\swoole\contract\TaskStoreInterface;
use think\swoole\task\RedisStore;
use Throwable;

class Task
{

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var TaskStoreInterface|null
     */
    protected $store;

    public function __construct(Manager $manager, Redis $redis)
    {
        $this->manager = $manager;
        $this->redis   = $redis;
    }

    /**
     * 创建可靠子协程任务
     */
    public function create(callable $callback, ?string $id = null): string
    {
        $config     = $this->getConfig();
        $store      = $this->getStore($config);
        $id         = $this->normalizeId($id);
        $heartbeat  = (int) ($config['heartbeat_interval'] ?? 5);
        $leaseTtl   = (int) ($config['lease_ttl'] ?? 15);
        $owner      = bin2hex(random_bytes(16));

        if ($heartbeat < 1 || $heartbeat >= $leaseTtl) {
            throw new InvalidArgumentException('task.heartbeat_interval must be positive and less than task.lease_ttl');
        }

        if (!$store->start($id, $owner, $leaseTtl)) {
            throw new RuntimeException("Task [{$id}] is already running");
        }

        $timerId = null;

        try {
            $timerId = $this->startHeartbeat($store, $id, $owner, $heartbeat, $leaseTtl);
            $runner  = function () use ($callback, $store, $id, $owner, $timerId) {
                try {
                    $callback();
                } catch (Throwable $e) {
                    $this->report($e);
                } finally {
                    $this->clearTimer($timerId);
                    $this->stopTask($store, $id, $owner);
                }
            };

            $this->runInCoroutine($runner);

            return $id;
        } catch (Throwable $e) {
            if ($timerId !== null) {
                $this->clearTimer($timerId);
            }
            $this->stopTask($store, $id, $owner);
            throw $e;
        }
    }

    /**
     * 判断任务是否仍在执行
     */
    public function isRunning(string $id): bool
    {
        return $this->getStore()->exists($id);
    }

    /**
     * 启动心跳
     *
     * @return int
     */
    protected function startHeartbeat(
        TaskStoreInterface $store,
        string $id,
        string $owner,
        int $interval,
        int $leaseTtl
    ): int {
        $reported = false;

        return $this->every($interval, function () use (&$reported, $store, $id, $owner, $leaseTtl) {
            try {
                $store->heartbeat($id, $owner, $leaseTtl);
                $reported = false;
            } catch (Throwable $e) {
                if (!$reported) {
                    $this->report($e);
                    $reported = true;
                }
            }
        });
    }

    protected function stopTask(TaskStoreInterface $store, string $id, string $owner): void
    {
        try {
            $store->stop($id, $owner);
        } catch (Throwable $e) {
            $this->report($e);
        }
    }

    /**
     * 创建任务协程
     *
     * @return int
     */
    protected function runInCoroutine(callable $callback): int
    {
        $coroutineId = Coroutine::create($callback);

        if ($coroutineId === false) {
            throw new RuntimeException('Unable to create task coroutine');
        }

        return $coroutineId;
    }

    /**
     * 注册周期定时器
     *
     * @return int
     */
    protected function every(int $seconds, callable $callback): int
    {
        $timerId = Timer::tick($seconds * 1000, $callback);

        if (!is_int($timerId)) {
            throw new RuntimeException('Unable to start task heartbeat');
        }

        return $timerId;
    }

    protected function clearTimer(int $timerId): void
    {
        Timer::clear($timerId);
    }

    protected function getStore(?array $config = null): TaskStoreInterface
    {
        if ($this->store instanceof TaskStoreInterface) {
            return $this->store;
        }

        $config = $config ?? $this->getConfig();

        return $this->store = $this->createStore($config);
    }

    protected function createStore(array $config): TaskStoreInterface
    {
        $redisConfig = is_array($config['redis'] ?? null) ? $config['redis'] : [];

        return new RedisStore(
            $this->redis->connection((string) ($redisConfig['connection'] ?? 'default')),
            (string) ($redisConfig['prefix'] ?? 'swoole:task:')
        );
    }

    protected function normalizeId(?string $id): string
    {
        $id = $id ?? $this->generateId();

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/', $id)) {
            throw new InvalidArgumentException('Task id must be 1-128 characters and contain only letters, numbers, dot, underscore, colon or dash');
        }

        return $id;
    }

    protected function getConfig(): array
    {
        $config = $this->manager->getConfig('task', []);

        return is_array($config) ? $config : [];
    }

    protected function report(Throwable $e): void
    {
        $this->manager->logServerError($e);
    }

    protected function generateId(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
