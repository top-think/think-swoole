<?php

use Smf\ConnectionPool\Connectors\ConnectorInterface;
use think\Container;
use think\swoole\facade\Redis as RedisFacade;
use think\swoole\Redis;
use think\swoole\redis\Connection;

function makeRedisService(?Closure $logger = null): Redis
{
    return new Redis([
        'default'     => 'default',
        'connections' => [
            'default' => [
                'host' => '127.0.0.1',
                'port' => 6379,
            ],
            'events'  => [
                'host'     => '127.0.0.1',
                'port'     => 6380,
                'database' => 2,
            ],
        ],
    ], $logger);
}

class RedisTestConnector implements ConnectorInterface
{
    public $connections = 0;

    public $disconnections = 0;

    public function connect(array $config)
    {
        $this->connections++;

        return new stdClass();
    }

    public function disconnect($connection)
    {
        $this->disconnections++;
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
}

it('reuses one connection object per name', function () {
    $redis = makeRedisService();

    expect($redis->connection())->toBe($redis->connection('default'))
        ->and($redis->connection('events'))->not->toBe($redis->connection('default'))
        ->and($redis->connection('events')->getName())->toBe('events');
});

it('rejects an explicitly unknown connection name', function () {
    $redis = makeRedisService();

    expect(fn () => $redis->connection('missing'))
        ->toThrow(InvalidArgumentException::class, 'Redis connection [missing] is not configured');
});

it('uses the default connection when only module-specific options are present', function () {
    $redis = makeRedisService();

    expect($redis->connectionFor([
        'prefix'     => 'swoole:test:',
        'max_active' => 2,
    ], 'task.redis'))
        ->toBe($redis->connection('default'));
});

it('keeps legacy inline config isolated and logs a deprecation warning', function () {
    $warnings = [];
    $redis    = makeRedisService(function (string $message) use (&$warnings) {
        $warnings[] = $message;
    });

    $config = [
        'host'       => '127.0.0.1',
        'port'       => 6381,
        'max_active' => 2,
    ];
    $legacy = $redis->connectionFor($config, 'ipc.redis');
    $again  = $redis->connectionFor($config, 'ipc.redis');

    expect($legacy->getName())->toBe('legacy.ipc.redis')
        ->and($again)->toBe($legacy)
        ->and($legacy)->not->toBe($redis->connection())
        ->and($warnings)->toHaveCount(1);
});

it('lets an explicit connection override legacy connection fields', function () {
    $redis = makeRedisService();

    expect($redis->connectionFor([
        'connection' => 'events',
        'host'       => '127.0.0.1',
        'port'       => 6381,
    ], 'ipc.redis'))->toBe($redis->connection('events'));
});

it('uses a dedicated connection outside the pool and closes it on failure', function () {
    $connector  = new RedisTestConnector();
    $connection = new Connection('default', [], $connector);

    try {
        $connection->dedicated(function () {
            throw new RuntimeException('failed');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('failed');
    }

    expect($connector->connections)->toBe(1)
        ->and($connector->disconnections)->toBe(1);
});

it('keeps a dedicated connection open while a Redis stream is consumed', function () {
    $connector  = new RedisTestConnector();
    $connection = new Connection('default', [], $connector);

    $stream = $connection->stream(function () {
        yield 1;
        yield 2;
    });

    expect($connector->connections)->toBe(0)
        ->and(iterator_to_array($stream))->toBe([1, 2])
        ->and($connector->connections)->toBe(1)
        ->and($connector->disconnections)->toBe(1);
});

it('closes a dedicated stream connection when consumption fails', function () {
    $connector  = new RedisTestConnector();
    $connection = new Connection('default', [], $connector);
    $stream     = $connection->stream(function () {
        yield 1;
        throw new RuntimeException('stream failed');
    });

    expect(fn () => iterator_to_array($stream))
        ->toThrow(RuntimeException::class, 'stream failed')
        ->and($connector->connections)->toBe(1)
        ->and($connector->disconnections)->toBe(1);
});

it('rejects generator callbacks from dedicated Redis APIs', function () {
    $connector  = new RedisTestConnector();
    $connection = new Connection('default', [], $connector);
    $callback   = fn () => (function () {
        yield 1;
    })();

    expect(fn () => $connection->dedicated($callback))
        ->toThrow(LogicException::class, 'must not return a Generator')
        ->and($connector->connections)->toBe(1)
        ->and($connector->disconnections)->toBe(1);
});

it('rejects generator callbacks from pooled Redis run', function () {
    if (!function_exists('Swoole\\Coroutine\\run')) {
        $this->markTestSkipped('Swoole coroutine runtime is required.');
    }

    $connector  = new RedisTestConnector();
    $connection = new Connection('default', [], $connector);
    $callback   = fn () => (function () {
        yield 1;
    })();

    \Swoole\Coroutine\run(function () use ($connection, $callback) {
        try {
            expect(fn () => $connection->run($callback))
                ->toThrow(LogicException::class, 'must not return a Generator');
        } finally {
            $pool = (new ReflectionMethod($connection, 'getPool'))->invoke($connection);
            $pool->close();
        }
    });

    expect($connector->connections)->toBe(1)
        ->and($connector->disconnections)->toBe(1);
});

it('requires blocking commands to use explicitly supported call paths', function () {
    $connection = new Connection('default', [], new RedisTestConnector());

    expect(fn () => $connection->__call('multi', []))
        ->toThrow(LogicException::class, 'must be executed through run() or dedicated()');
});

it('proxies facade calls to the shared redis service', function () {
    $app       = app();
    $container = new Container();
    $service   = new class {
        public function connection(?string $name = null): Connection
        {
            return new Connection($name ?: 'default', []);
        }
    };

    $container->bind(Redis::class, function () use ($service) {
        return $service;
    });
    Container::setInstance($container);

    try {
        expect(RedisFacade::connection()->getName())->toBe('default')
            ->and(RedisFacade::connection('events')->getName())->toBe('events');
    } finally {
        Container::setInstance($app);
    }
});
