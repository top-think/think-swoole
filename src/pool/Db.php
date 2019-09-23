<?php

namespace think\swoole\pool;

use Swoole\Coroutine\Channel;
use think\Config;
use think\db\ConnectionInterface;
use think\swoole\pool\db\Connection;

/**
 * Class Db
 * @package think\swoole\pool
 * @property Config $config
 */
class Db extends \think\Db
{
    /** @var Channel[] */
    protected $pools = [];

    protected $connectionCount = [];

    protected function getMaxActive()
    {
        return $this->config->get('swoole.connection_pool.max_active', 3);
    }

    protected function getMaxWaitTime()
    {
        return $this->config->get('swoole.connection_pool.max_wait_time', 3);
    }

    /**
     * 创建数据库连接实例
     * @access protected
     * @param string|null $name  连接标识
     * @param bool        $force 强制重新连接
     * @return ConnectionInterface
     */
    protected function instance(string $name = null, bool $force = false): ConnectionInterface
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        $pool = $this->getPool($name);

        if (!isset($this->connectionCount[$name])) {
            $this->connectionCount[$name] = 0;
        }

        if ($this->connectionCount[$name] < $this->getMaxActive()) {
            //新建
            if (!$force) {
                $this->connectionCount[$name]++;
            }
            return new Connection($this->createConnection($name), $pool, !$force);
        }

        $connection = $pool->pop($this->getMaxWaitTime());

        if ($connection === false) {
            throw new \RuntimeException(sprintf(
                'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                $this->getMaxWaitTime(),
                $pool->length(),
                $this->connectionCount[$name] ?? 0
            ));
        }

        return new Connection($connection, $pool);
    }

    protected function getConnectionConfig(string $name): array
    {
        $config = parent::getConnectionConfig($name);

        //打开断线重连
        $config['break_reconnect'] = true;
        return $config;
    }

    /**
     * 获取连接池
     * @param $name
     * @return Channel
     */
    protected function getPool($name)
    {
        if (empty($this->pools[$name])) {
            $this->pools[$name] = new Channel($this->getMaxActive());
        }
        return $this->pools[$name];
    }
}
