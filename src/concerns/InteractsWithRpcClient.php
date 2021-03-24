<?php

namespace think\swoole\concerns;

use Generator;
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
use Throwable;

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
    protected function prepareRpcClient()
    {
        $this->onEvent('workerStart', function () {
            $this->bindRpcClientPool();
            $this->bindRpcInterface();
        });
    }

    protected function bindRpcInterface()
    {
        //引入rpc接口文件
        if (file_exists($rpc = $this->container->getBasePath() . 'rpc.php')) {
            /** @noinspection PhpIncludeInspection */
            $rpcServices = (array) include $rpc;

            //绑定rpc接口
            try {
                foreach ($rpcServices as $name => $abstracts) {
                    $parserClass = $this->getConfig("rpc.client.{$name}.parser", JsonParser::class);
                    $parser      = $this->getApplication()->make($parserClass);
                    $gateway     = new Gateway($this->createRpcConnector($name), $parser);
                    $middleware  = $this->getConfig("rpc.client.{$name}.middleware", []);

                    foreach ($abstracts as $abstract) {
                        $this->getApplication()->bind($abstract, function (App $app) use ($middleware, $gateway, $name, $abstract) {
                            return $app->invokeClass(Proxy::getClassName($name, $abstract), [$gateway, $middleware]);
                        });
                    }
                }
            } catch (Throwable $e) {
            }
        }
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
                if (!$data instanceof Generator) {
                    $data = [$data];
                }

                /** @var \Swoole\Coroutine\Client $client */
                $client = $this->pool->borrow();

                try {
                    foreach ($data as $string) {
                        if (!$client->send($string)) {
                            $this->onError($client);
                        }
                    }

                    $response = $client->recv();

                    if ($response === false || empty($response)) {
                        $this->onError($client);
                    }

                    return $response;
                } finally {
                    $this->pool->return($client);
                }
            }

            protected function onError(\Swoole\Coroutine\Client $client)
            {
                $client->close();
                throw new RpcClientException(swoole_strerror($client->errCode), $client->errCode);
            }
        };
    }
}
