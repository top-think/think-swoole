<?php

namespace think\swoole\concerns;

use Swoole\Server;
use think\App;
use think\Event;
use think\helper\Str;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\rpc\client\Pool;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\server\Dispatcher;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @method Server getServer()
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
            $this->app->instance(Pool::class, new Pool($clients));
            //引入rpc接口文件
            if (file_exists($rpc = $this->app->getBasePath() . 'rpc.php')) {
                include_once $rpc;
            }
        }
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
