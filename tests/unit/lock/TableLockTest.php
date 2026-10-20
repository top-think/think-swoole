<?php

use think\swoole\lock\Table;
use think\swoole\contract\LockInterface;

class LockTableTestStore
{
    public $rows = [];

    public function get(string $name)
    {
        return $this->rows[$name] ?? false;
    }

    public function set(string $name, array $value): void
    {
        $this->rows[$name] = $value;
    }

    public function del(string $name): void
    {
        unset($this->rows[$name]);
    }
}

class TableLockTestDriver extends Table
{
    public $tokens = [];

    protected $store;

    public function __construct(LockTableTestStore $locks)
    {
        $this->store = $locks;
    }

    protected function getLock(string $name)
    {
        return $this->store->get($name);
    }

    protected function setLock(string $name, array $value): void
    {
        $this->store->set($name, $value);
    }

    protected function deleteLock(string $name): void
    {
        $this->store->del($name);
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

    public function useToken($name, string $token): void
    {
        $this->tokens[$name] = $token;
    }
}

it('stores and releases an owned table lock', function () {
    $store      = new LockTableTestStore();
    $lock       = new TableLockTestDriver($store);

    expect($lock->lock('order', 30))->toBeTrue()
        ->and($store->rows['order']['owner'] ?? null)->toBe($lock->tokens['order'] ?? null)
        ->and($lock->unlock('order'))->toBeTrue()
        ->and($store->rows)->toBe([])
        ->and($lock->tokens)->toBe([]);
});

it('does not remove a table lock owned by another token', function () {
    $store = new LockTableTestStore();
    $first = new TableLockTestDriver($store);
    $first->lock('order', 30);

    $second = new TableLockTestDriver($store);
    $second->useToken('order', 'another-owner');

    expect($second->unlock('order'))->toBeFalse()
        ->and($store->rows['order']['owner'] ?? null)->toBe($first->tokens['order'] ?? null);
});

it('reclaims an expired table lock', function () {
    $store = new LockTableTestStore();
    $store->rows['order'] = [
        'time'  => time() - 60,
        'owner' => 'expired-owner',
    ];
    $lock = new TableLockTestDriver($store);

    expect($lock->lock('order', 30))->toBeTrue()
        ->and($store->rows['order']['owner'])->toBe($lock->tokens['order']);
});

it('rejects a non-positive table lock expiration', function () {
    $lock = new TableLockTestDriver(new LockTableTestStore());

    expect(fn () => $lock->lock('order', 0))
        ->toThrow(InvalidArgumentException::class, 'Lock expiration must be a positive integer');
});

it('uses the shared 250ms lock retry interval', function () {
    expect(LockInterface::RETRY_INTERVAL)->toBe(0.25);
});
