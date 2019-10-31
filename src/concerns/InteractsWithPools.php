<?php

namespace think\swoole\concerns;

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Server;
use think\App;
use think\helper\Arr;
use think\swoole\Pool;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @method Server getServer()
 */
trait InteractsWithPools
{
    /**
     * @return Pool
     */
    public function getPools()
    {
        return $this->app->make(Pool::class);
    }

    protected function preparePools()
    {
        $createPools = function () {
            /** @var Pool $pool */
            $pools = $this->getPools();

            foreach ($this->getConfig('pool', []) as $name => $config) {
                $type = Arr::get($config, 'type');
                if ($type && is_subclass_of($type, ConnectorInterface::class)) {
                    $pool = new ConnectionPool(
                        Pool::pullPoolConfig($config),
                        $this->app->make($type),
                        $config
                    );
                    $pools->add($name, $pool);
                    //注入到app
                    $this->app->instance("swoole.pool.{$name}", $pool);
                }
            }
        };

        $closePools = function () {
            $this->getPools()->closeAll();
        };

        $this->onEvent('workerStart', $createPools);
        $this->onEvent('workerStop', $closePools);
        $this->onEvent('WorkerError', $closePools);
        $this->onEvent('WorkerExit', $closePools);
    }
}
