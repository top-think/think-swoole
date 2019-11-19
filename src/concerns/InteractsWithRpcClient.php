<?php

namespace think\swoole\concerns;

use Smf\ConnectionPool\ConnectionPool;
use Swoole\Server;
use think\App;
use think\swoole\exception\RpcClientException;
use think\swoole\Pool;
use think\swoole\pool\Client;
use think\swoole\rpc\client\Connector;
use think\swoole\rpc\client\Gateway;
use think\swoole\rpc\client\Proxy;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\Packer;

/**
 * Trait InteractsWithRpcClient
 * @package think\swoole\concerns
 * @property App $app
 * @property App $container
 * @method Server getServer()
 * @method Pool getPools()
 */
trait InteractsWithRpcClient
{
    protected $rpcServices = [];

    protected function prepareRpcClient()
    {
        //引入rpc接口文件
        if (file_exists($rpc = $this->container->getBasePath() . 'rpc.php')) {
            $this->rpcServices = (array) include $rpc;
        }

        $this->onEvent('workerStart', function () {
            $this->bindRpcClientPool();
        });
    }

    protected function bindRpcClientPool()
    {
        if (!empty($clients = $this->getConfig('rpc.client'))) {
            //创建client连接池
            foreach ($clients as $name => $config) {
                $pool = new ConnectionPool(
                    Pool::pullPoolConfig($config),
                    new Client(),
                    array_merge(
                        $config,
                        [
                            'open_length_check'     => true,
                            'package_length_type'   => Packer::HEADER_PACK,
                            'package_length_offset' => 0,
                            'package_body_offset'   => 8,
                        ]
                    )
                );
                $this->getPools()->add("rpc.client.{$name}", $pool);
            }

            //绑定rpc接口
            try {
                foreach ($this->rpcServices as $name => $abstracts) {
                    $parserClass = $this->getConfig("rpc.client.{$name}.parser", JsonParser::class);
                    $parser      = $this->app->make($parserClass);
                    $gateway     = new Gateway($this->createRpcConnector($name), $parser);

                    foreach ($abstracts as $abstract) {
                        $this->app->bind($abstract, function () use ($gateway, $name, $abstract) {
                            return $this->app->invokeClass(Proxy::getClassName($name, $abstract), [$gateway]);
                        });
                    }
                }
            } catch (\Exception | \Throwable $e) {

            }
        }
    }

    protected function createRpcConnector($name)
    {
        $pool = $this->getPools()->get("rpc.client.{$name}");

        return new class($pool) implements Connector {
            protected $pool;

            public function __construct(ConnectionPool $pool)
            {
                $this->pool = $pool;
            }

            public function sendAndRecv($data)
            {
                if (!$data instanceof \Generator) {
                    $data = [$data];
                }

                /** @var \Swoole\Coroutine\Client $client */
                $client = $this->pool->borrow();

                try {
                    foreach ($data as $string) {
                        if (!$client->send($string)) {
                            throw new RpcClientException(swoole_strerror($client->errCode), $client->errCode);
                        }
                    }

                    $response = $client->recv();

                    if ($response === false || empty($response)) {
                        throw new RpcClientException(swoole_strerror($client->errCode), $client->errCode);
                    }

                    return $response;
                } finally {
                    $this->pool->return($client);
                }
            }

        };
    }

}
