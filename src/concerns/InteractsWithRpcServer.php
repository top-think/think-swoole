<?php

namespace think\swoole\concerns;

use Swoole\Server;
use think\App;
use think\swoole\Pool;
use think\swoole\rpc\Manager;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @property App $container
 * @method Server getServer()
 * @method Pool getPools()
 */
trait InteractsWithRpcServer
{

    protected function prepareRpcServer()
    {
        if ($this->getConfig('rpc.server.enable', false)) {
            $host = $this->getConfig('server.host');
            $port = $this->getConfig('rpc.server.port', 9000);

            $rpcServer = $this->getServer()->addlistener($host, $port, SWOOLE_SOCK_TCP);

            /** @var Manager $rpcManager */
            $rpcManager = $this->container->make(Manager::class);

            $rpcManager->attachToServer($rpcServer);
        }
    }

}
