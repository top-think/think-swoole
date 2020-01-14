<?php

namespace think\swoole\pool;

use think\Config;
use think\db\ConnectionInterface;
use think\swoole\concerns\InteractsWithPool;
use think\swoole\coroutine\Context;

/**
 * Class Db
 * @package think\swoole\pool
 * @property Config $config
 */
class Db extends \think\Db
{
    use InteractsWithPool;

    protected function getPoolMaxActive($name): int
    {
        return $this->config->get('swoole.pool.db.max_active', 3);
    }

    protected function getPoolMaxWaitTime($name): int
    {
        return $this->config->get('swoole.pool.db.max_wait_time', 3);
    }

    /**
     * 创建数据库连接实例
     * @access protected
     * @param string|null $name 连接标识
     * @param bool $force 强制重新连接
     * @return ConnectionInterface
     */
    protected function instance(string $name = null, bool $force = false): ConnectionInterface
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if ($force) {
            return $this->createConnection($name);
        }

        return Context::rememberData("db.connection.{$name}", function () use ($name) {
            return $this->getPoolConnection($name);
        });
    }

    protected function createPoolConnection(string $name)
    {
        return $this->createConnection($name);
    }

    protected function getConnectionConfig(string $name): array
    {
        $config = parent::getConnectionConfig($name);

        //打开断线重连
        $config['break_reconnect'] = true;
        return $config;
    }

}
