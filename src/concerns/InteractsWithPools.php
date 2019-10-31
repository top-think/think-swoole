<?php

namespace think\swoole\concerns;

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\ConnectionPoolTrait;
use Smf\ConnectionPool\Connectors\ConnectorInterface;
use Swoole\Server;
use think\App;
use think\helper\Arr;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @method Server getServer()
 */
trait InteractsWithPools
{
    use ConnectionPoolTrait;

    public function pullConnectionPoolConfig(&$config)
    {
        return [
            'minActive'         => Arr::pull($config, 'min_active', 10),
            'maxActive'         => Arr::pull($config, 'max_active', 30),
            'maxWaitTime'       => Arr::pull($config, 'max_wait_time', 5),
            'maxIdleTime'       => Arr::pull($config, 'max_idle_time', 20),
            'idleCheckInterval' => Arr::pull($config, 'idle_check_interval', 10),
        ];
    }

    protected function preparePools()
    {
        $createPools = function () {
            $pools = $this->getConfig('pool', []);

            foreach ($pools as $name => $config) {
                $type = Arr::get($config, 'type');
                if ($type && $type instanceof ConnectorInterface) {
                    $pool = new ConnectionPool(
                        $this->pullConnectionPoolConfig($config),
                        $this->app->make($type),
                        $config
                    );

                    $pool->init();
                    $this->addConnectionPool($name, $pool);
                }
            }
        };

        $closePools = function () {
            $this->closeConnectionPools();
        };

        $this->onEvent('workerStart', $createPools);
        $this->onEvent('workerStop', $closePools);
        $this->onEvent('WorkerError', $closePools);
        $this->onEvent('WorkerExit', $closePools);
    }
}
