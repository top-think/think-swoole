<?php

namespace think\swoole\pool;

use think\Config;
use think\db\ConnectionInterface;
use think\swoole\pool\proxy\Connection;

/**
 * Class Db
 * @package think\swoole\pool
 * @property Config $config
 */
class Db extends \think\Db
{

    protected function createConnection(string $name): ConnectionInterface
    {
        return new Connection(new class(function () use ($name) {
            return parent::createConnection($name);
        }) extends Connector {
            public function disconnect($connection)
            {
                if ($connection instanceof ConnectionInterface) {
                    $connection->close();
                }
            }
        }, $this->config->get('swoole.pool.db', []));
    }

}
