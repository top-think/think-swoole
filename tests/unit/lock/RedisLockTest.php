<?php

use think\swoole\lock\Redis as RedisLock;
use think\swoole\Lock;
use think\swoole\Manager;

class RedisLockTestManager extends Manager
{
}

class RedisLockTestDriver extends RedisLock
{
    public $attempts = 0;

    public $waits = 0;

    public $acquireResults = [];

    public $releaseResult = true;

    public $tokens = [];

    protected function acquire(string $key, string $token, int $expire): bool
    {
        $this->attempts++;

        return array_shift($this->acquireResults) ?? false;
    }

    protected function release(string $key, string $token): bool
    {
        return $this->releaseResult;
    }

    protected function wait(): void
    {
        $this->waits++;
    }

    protected function saveOwnerToken($name, string $token): void
    {
        $this->tokens[$name] = $token;
    }

    protected function getOwnerToken($name): ?string
    {
        return $this->tokens[$name] ?? null;
    }

    protected function removeOwnerToken($name): void
    {
        unset($this->tokens[$name]);
    }

    public function resolveKey($name): string
    {
        return $this->getKey($name);
    }
}

function makeRedisLock(array $config = []): RedisLockTestDriver
{
    return new RedisLockTestDriver($config, new RedisLockTestManager(app()));
}

it('waits until the redis lock is acquired', function () {
    $lock                 = makeRedisLock();
    $lock->acquireResults = [false, false, true];

    expect($lock->lock('order', 30))->toBeTrue()
        ->and($lock->attempts)->toBe(3)
        ->and($lock->waits)->toBe(2)
        ->and($lock->tokens['order'] ?? null)->not->toBeNull();
});

it('releases an owned lock only once', function () {
    $lock                 = makeRedisLock();
    $lock->acquireResults = [true];
    $lock->lock('order');

    expect($lock->unlock('order'))->toBeTrue()
        ->and($lock->unlock('order'))->toBeFalse();
});

it('does not release a lock without an owner token', function () {
    $lock = makeRedisLock();

    expect($lock->unlock('order'))->toBeFalse();
});

it('keeps the owner token when redis rejects release', function () {
    $lock                 = makeRedisLock();
    $lock->acquireResults = [true];
    $lock->releaseResult  = false;
    $lock->lock('order');

    expect($lock->unlock('order'))->toBeFalse()
        ->and($lock->tokens['order'] ?? null)->not->toBeNull();
});

it('rejects a non-positive lock expiration', function () {
    $lock = makeRedisLock();

    expect(fn () => $lock->lock('order', 0))
        ->toThrow(InvalidArgumentException::class, 'Lock expiration must be a positive integer');
});

it('builds the lock key from the configured prefix', function () {
    $lock = makeRedisLock(['prefix' => 'custom:lock:']);

    expect($lock->resolveKey('order'))->toBe('custom:lock:order');
});

it('resolves the redis driver through the lock manager', function () {
    $app      = app();
    $original = $app->config->get('swoole');
    $manager  = new RedisLockTestManager($app);

    $app->instance(Manager::class, $manager);
    $app->config->set([
        'lock' => [
            'enable' => true,
            'type'   => 'redis',
            'redis'  => [
                'connection' => 'default',
                'prefix'     => 'swoole:lock:',
            ],
        ],
    ], 'swoole');

    try {
        $lock = new Lock($app);
        $lock->prepare();

        $property = new ReflectionProperty(Lock::class, 'drivers');
        $property->setAccessible(true);
        $drivers = $property->getValue($lock);

        expect($drivers['redis'] ?? null)->toBeInstanceOf(RedisLock::class);
    } finally {
        $app->config->set($original, 'swoole');
        $app->delete(Manager::class);
    }
});
