<?php

namespace think\swoole\lock;

use InvalidArgumentException;
use Swoole\Coroutine;
use Swoole\Table as SwooleTable;
use think\swoole\contract\LockInterface;

class Table implements LockInterface
{
    use OwnerToken;

    /**
     * @var SwooleTable
     */
    protected $locks;

    public function prepare()
    {
        $this->locks = new SwooleTable(1024);
        $this->locks->column('time', SwooleTable::TYPE_INT);
        $this->locks->column('owner', SwooleTable::TYPE_STRING, 64);
        $this->locks->create();
    }

    public function lock($name, $expire = 60)
    {
        $expire = (int) $expire;
        if ($expire < 1) {
            throw new InvalidArgumentException('Lock expiration must be a positive integer');
        }

        $token = $this->createOwnerToken();

        while (true) {
            $time = time();
            $lock = $this->getLock($name);
            if (!$lock || $lock['time'] <= $time - $expire) {
                $this->setLock($name, [
                    'time'  => $time,
                    'owner' => $token,
                ]);
                $this->saveOwnerToken($name, $token);
                return true;
            }

            $this->wait();
        }
    }

    public function unlock($name)
    {
        $token = $this->getOwnerToken($name);
        if ($token === null) {
            return false;
        }

        $lock = $this->getLock($name);
        if (!$lock || $lock['owner'] !== $token) {
            return false;
        }

        $this->deleteLock($name);
        $this->removeOwnerToken($name);

        return true;
    }

    protected function getLock(string $name)
    {
        return $this->locks->get($name);
    }

    protected function setLock(string $name, array $value): void
    {
        $this->locks->set($name, $value);
    }

    protected function deleteLock(string $name): void
    {
        $this->locks->del($name);
    }

    protected function wait(): void
    {
        Coroutine::sleep(self::RETRY_INTERVAL);
    }

}
