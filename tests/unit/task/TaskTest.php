<?php

use think\App;
use think\Container;
use think\swoole\contract\TaskStoreInterface;
use think\swoole\facade\Task as TaskFacade;
use think\swoole\Manager;
use think\swoole\Redis;
use think\swoole\redis\Connection;
use think\swoole\Task;
use think\swoole\task\RedisStore;

class TaskTestManager extends Manager
{
    public $heartbeatInterval = 5;

    public $leaseTtl = 15;

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function getConfig(string $name, $default = null)
    {
        if ($name === 'task') {
            return [
                'heartbeat_interval' => $this->heartbeatInterval,
                'lease_ttl'          => $this->leaseTtl,
            ];
        }

        return parent::getConfig($name, $default);
    }
}

class TaskTestStore implements TaskStoreInterface
{
    public $records = [];

    public $starts = 0;

    public $heartbeats = 0;

    public $stops = 0;

    public $failOnStart = false;

    public function start(string $id, string $owner, int $leaseTtl): bool
    {
        if ($this->failOnStart || isset($this->records[$id])) {
            return false;
        }

        $this->starts++;
        $this->records[$id] = $owner;

        return true;
    }

    public function heartbeat(string $id, string $owner, int $leaseTtl): bool
    {
        if (($this->records[$id] ?? null) !== $owner) {
            return false;
        }

        $this->heartbeats++;

        return true;
    }

    public function stop(string $id, string $owner): bool
    {
        if (($this->records[$id] ?? null) !== $owner) {
            return false;
        }

        $this->stops++;
        unset($this->records[$id]);

        return true;
    }

    public function exists(string $id): bool
    {
        return isset($this->records[$id]);
    }
}

class TaskTestService extends Task
{
    public $executeCoroutine = true;

    public $coroutineResult = 1;

    public $timerId = 99;

    public $timerCallback;

    public $clearedTimers = [];

    public $reported = [];

    protected $testStore;

    public function __construct(Manager $manager, TaskStoreInterface $store)
    {
        parent::__construct($manager, new Redis());

        $this->testStore = $store;
    }

    protected function createStore(array $config): TaskStoreInterface
    {
        return $this->testStore;
    }

    protected function generateId(): string
    {
        return 'generated-id';
    }

    protected function runInCoroutine(callable $callback): int
    {
        if ($this->executeCoroutine) {
            $callback();
        }

        return $this->coroutineResult;
    }

    protected function every(int $seconds, callable $callback): int
    {
        $this->timerCallback = $callback;

        return $this->timerId;
    }

    protected function clearTimer(int $timerId): void
    {
        $this->clearedTimers[] = $timerId;
    }

    protected function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function heartbeat(): void
    {
        ($this->timerCallback)();
    }

}

class TaskStoreConfigService extends Task
{
    public function makeStore(array $config): TaskStoreInterface
    {
        return $this->createStore($config);
    }
}

class TaskTestRedis extends Redis
{
    public $requestedConnection;

    public function connection(?string $name = null): Connection
    {
        $this->requestedConnection = $name;

        return parent::connection($name);
    }
}

function makeTaskTestService(TaskTestStore $store): TaskTestService
{
    return new TaskTestService(new TaskTestManager(app()), $store);
}

it('tracks a task until it completes', function () {
    $store = new TaskTestStore();
    $task  = makeTaskTestService($store);

    $id = $task->create(function () {
    });

    expect($id)->toBe('generated-id')
        ->and($store->starts)->toBe(1)
        ->and($store->stops)->toBe(1)
        ->and($task->clearedTimers)->toBe([99])
        ->and($task->isRunning($id))->toBeFalse();
});

it('logs task exceptions and removes the heartbeat', function () {
    $store     = new TaskTestStore();
    $task      = makeTaskTestService($store);
    $exception = new RuntimeException('task failed');

    $id = $task->create(function () use ($exception) {
        throw $exception;
    });

    expect($task->reported)->toBe([$exception])
        ->and($store->stops)->toBe(1)
        ->and($task->isRunning($id))->toBeFalse();
});

it('does not execute a task when its heartbeat already exists', function () {
    $store = new TaskTestStore();
    $store->failOnStart = true;
    $task = makeTaskTestService($store);
    $ran  = false;

    try {
        $task->create(function () use (&$ran) {
            $ran = true;
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Task [generated-id] is already running');
    }

    expect($ran)->toBeFalse();
});

it('renews the heartbeat while a task keeps running', function () {
    $store = new TaskTestStore();
    $task  = makeTaskTestService($store);
    $task->executeCoroutine = false;

    $id = $task->create(function () {
    });

    expect($task->isRunning($id))->toBeTrue();

    $task->heartbeat();

    expect($store->heartbeats)->toBe(1)
        ->and($task->isRunning($id))->toBeTrue();
});

it('reports an unknown or expired task as not running', function () {
    $task = makeTaskTestService(new TaskTestStore());

    expect($task->isRunning('missing'))->toBeFalse();
});

it('is constructed by the container with manager and redis dependencies', function () {
    $manager   = new TaskTestManager(app());
    $container = new Container();
    $container->instance(Manager::class, $manager);
    $container->instance(Redis::class, new Redis());

    expect($container->make(Task::class))->toBeInstanceOf(Task::class);
});

it('uses task.redis connection and prefix directly', function () {
    $redis = new TaskTestRedis([
        'default'     => 'default',
        'connections' => [
            'default' => [],
            'events'  => [],
        ],
    ]);
    $task  = new TaskStoreConfigService(new TaskTestManager(app()), $redis);
    $store = $task->makeStore([
        'prefix' => 'legacy:',
        'redis'  => [
            'connection' => 'events',
            'prefix'     => 'nested:',
        ],
    ]);

    $property = new ReflectionProperty(RedisStore::class, 'prefix');
    $property->setAccessible(true);

    expect($redis->requestedConnection)->toBe('events')
        ->and($property->getValue($store))->toBe('nested:');
});

it('uses the default task redis connection and prefix', function () {
    $redis = new TaskTestRedis([
        'default'     => 'default',
        'connections' => [
            'default' => [],
        ],
    ]);
    $task  = new TaskStoreConfigService(new TaskTestManager(app()), $redis);
    $store = $task->makeStore([]);

    $property = new ReflectionProperty(RedisStore::class, 'prefix');
    $property->setAccessible(true);

    expect($redis->requestedConnection)->toBe('default')
        ->and($property->getValue($store))->toBe('swoole:task:');
});

it('rejects an unknown task redis connection', function () {
    $redis = new TaskTestRedis([
        'default'     => 'default',
        'connections' => [
            'default' => [],
        ],
    ]);
    $task = new TaskStoreConfigService(new TaskTestManager(app()), $redis);

    expect(fn () => $task->makeStore([
        'redis' => ['connection' => 'missing'],
    ]))->toThrow(InvalidArgumentException::class, 'Redis connection [missing] is not configured');
});

it('accepts a custom id and rejects invalid global timing config', function () {
    $store = new TaskTestStore();
    $task  = makeTaskTestService($store);

    expect($task->create(function () {
    }, 'order-100'))->toBe('order-100');

    $manager                    = new TaskTestManager(app());
    $manager->heartbeatInterval = 15;
    $manager->leaseTtl          = 15;
    $invalidTask                = new TaskTestService($manager, new TaskTestStore());

    $invalidTask->create(function () {
    }, 'invalid-lease');
})->throws(InvalidArgumentException::class, 'task.heartbeat_interval must be positive and less than task.lease_ttl');

it('proxies facade calls to one shared task service', function () {
    $app       = app();
    $container = new Container();
    $service   = new class {
        public $calls = 0;

        public function create(callable $callback, ?string $id = null): string
        {
            $this->calls++;

            return 'facade-task';
        }

        public function isRunning(string $id): bool
        {
            return true;
        }
    };

    $container->bind(Task::class, function () use ($service) {
        return $service;
    });
    Container::setInstance($container);

    try {
        expect(TaskFacade::create(function () {
        }))->toBe('facade-task')
            ->and(TaskFacade::isRunning('facade-task'))->toBeTrue()
            ->and($service->calls)->toBe(1);
    } finally {
        Container::setInstance($app);
    }
});
