<?php

namespace think\swoole\concerns;

use Smf\ConnectionPool\ConnectionPool;
use Swoole\Server;
use think\App;
use think\Event;
use think\helper\Str;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\exception\RpcClientException;
use think\swoole\Pool;
use think\swoole\pool\Client;
use think\swoole\rpc\client\Connector;
use think\swoole\rpc\client\Proxy;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\server\Dispatcher;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @method Server getServer()
 * @method Pool getPools()
 */
trait InteractsWithRpc
{

    protected $rpcEvents = [
        'connect',
        'receive',
        'close',
    ];

    protected $isRpcServer = false;

    protected function prepareRpc()
    {
        if ($this->isRpcServer = $this->getConfig('rpc.server.enable', false)) {
            $host = $this->getConfig('server.host');
            $port = $this->getConfig('rpc.server.port', 9000);

            $rpcServer = $this->getServer()->addlistener($host, $port, SWOOLE_SOCK_TCP);

            $rpcServer->set([
                'open_eof_check' => true,
                'open_eof_split' => true,
                'package_eof'    => ParserInterface::EOF,
            ]);

            $this->setRpcServerListeners($rpcServer);
        }

        $this->onEvent('workerStart', function () {
            if ($this->isRpcServer) {
                $this->bindRpcParser();
                $this->bindRpcDispatcher();
            }
            $this->bindRpcClientPool();
        });
    }

    /**
     * @param Server\Port $server
     */
    protected function setRpcServerListeners($server)
    {
        foreach ($this->rpcEvents as $event) {
            $listener = Str::camel("on_rpc_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->triggerEvent("rpc." . $event, func_get_args());
            };

            $server->on($event, $callback);
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
                            'open_eof_check' => true,
                            'open_eof_split' => true,
                            'package_eof'    => ParserInterface::EOF,
                        ]
                    )
                );
                $this->getPools()->add("rpc.client.{$name}", $pool);
            }

            //引入rpc接口文件
            if (file_exists($rpc = $this->app->getBasePath() . 'rpc.php')) {
                try {
                    $services = include $rpc;
                    foreach ((array) $services as $name => $abstracts) {
                        foreach ($abstracts as $abstract) {
                            $this->app->bind($abstract, function () use ($name, $abstract) {

                                $connector   = $this->createRpcConnector($name);
                                $parserClass = $this->getConfig("rpc.client.{$name}.parser", JsonParser::class);
                                $parser      = $this->app->make($parserClass);

                                return $this->app->invokeClass(Proxy::getClassName($name, $abstract), [$connector, $parser]);
                            });
                        }
                    }
                } catch (\Exception|\Throwable $e) {

                }
            }
        }
    }

    protected function createRpcConnector($name)
    {
        $pool = $this->getPools()->get("rpc.client.{$name}");

        return new class($pool) implements Connector
        {
            protected $pool;

            public function __construct(ConnectionPool $pool)
            {
                $this->pool = $pool;
            }

            public function sendAndRecv($data)
            {
                /** @var \Swoole\Coroutine\Client $client */
                $client = $this->pool->borrow();

                try {
                    if (!$client->send($data)) {
                        throw new RpcClientException(swoole_strerror($client->errCode), $client->errCode);
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

    protected function bindRpcDispatcher()
    {
        $services = $this->getConfig('rpc.server.services', []);

        $this->app->make(Dispatcher::class, [$services]);
    }

    protected function bindRpcParser()
    {
        $parserClass = $this->getConfig('rpc.server.parser', JsonParser::class);

        $this->app->bind(ParserInterface::class, $parserClass);
        $this->app->make(ParserInterface::class);
    }

    public function onRpcConnect(Server $server, int $fd, int $reactorId)
    {
        $args = func_get_args();
        $this->runInSandbox(function (Event $event, Dispatcher $dispatcher) use ($fd, $server, $args) {
            $event->trigger("swoole.rpc.Connect", $args);
        }, $fd, true);
    }

    public function onRpcReceive(Server $server, $fd, $reactorId, $data)
    {
        $data = rtrim($data, ParserInterface::EOF);
        $this->runInSandbox(function (Dispatcher $dispatcher) use ($fd, $data, $server) {
            $dispatcher->dispatch($fd, $data);
        }, $fd, true);
    }

    public function onRpcClose(Server $server, int $fd, int $reactorId)
    {
        $args = func_get_args();
        $this->runInSandbox(function (Event $event) use ($args) {
            $event->trigger("swoole.rpc.Close", $args);
        }, $fd);
    }

}
