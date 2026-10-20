<?php

namespace think\swoole\lock;

use think\swoole\coroutine\Context;

trait OwnerToken
{
    protected function createOwnerToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    protected function saveOwnerToken($name, string $token): void
    {
        Context::setData($this->getOwnerTokenKey($name), $token);
    }

    protected function getOwnerToken($name): ?string
    {
        return Context::getData($this->getOwnerTokenKey($name));
    }

    protected function removeOwnerToken($name): void
    {
        Context::removeData($this->getOwnerTokenKey($name));
    }

    protected function getOwnerTokenKey($name): string
    {
        return 'swoole.lock.owner.' . static::class . ':' . $name;
    }
}
