<?php

namespace think\swoole;

use Swoole\Coroutine;
use Swoole\Server;
use Swoole\Server\Port;
use think\App;
use think\Event;
use think\helper\Str;
use think\swoole\concerns\InteractsWithPools;
use think\swoole\concerns\InteractsWithRpcClient;
use think\swoole\concerns\InteractsWithServer;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\concerns\WithApplication;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\Packer;
use think\swoole\rpc\server\Channel;
use think\swoole\rpc\server\Dispatcher;
use Throwable;

class RpcManager
{
    use InteractsWithServer,
        InteractsWithSwooleTable,
        InteractsWithPools,
        InteractsWithRpcClient,
        WithApplication;

    /**
     * @var App
     */
    protected $container;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'workerError',
        'workerExit',
        'packet',
        'task',
        'finish',
        'pipeMessage',
        'managerStart',
        'managerStop',
    ];

    protected $rpcEvents = [
        'connect',
        'receive',
        'close',
    ];

    /** @var Channel[] */
    protected $channels = [];

    /**
     * Manager constructor.
     * @param App $container
     */
    public function __construct(App $container)
    {
        $this->container = $container;
    }

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->events = array_merge($this->events ?? [], $this->rpcEvents);
        $this->prepareTables();
        $this->preparePools();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareRpcClient();
    }

    protected function prepareRpcServer()
    {
        $this->onEvent('workerStart', function () {
            $this->bindRpcParser();
            $this->bindRpcDispatcher();
        });
    }

    public function attachToServer(Port $port)
    {
        $port->set([]);
        foreach ($this->rpcEvents as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->triggerEvent("rpc." . $event, func_get_args());
            };

            $port->on($event, $callback);
        }

        $this->onEvent('workerStart', function (App $app) {
            $this->app = $app;
        });
        $this->prepareRpcServer();
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

    public function onConnect(Server $server, int $fd, int $reactorId)
    {
        $args = func_get_args();
        $this->runInSandbox(function (Event $event) use ($args) {
            $event->trigger("swoole.rpc.Connect", $args);
        }, $fd, true);
    }

    protected function recv(Server $server, $fd, $data, $callback)
    {
        if (!isset($this->channels[$fd]) || empty($handle = $this->channels[$fd]->pop())) {
            //解析包头
            try {
                [$header, $data] = Packer::unpack($data);
            } catch (Throwable $e) {
                //错误的包头
                return $server->close($fd);
            }

            $this->channels[$fd] = new Channel($header);

            $handle = $this->channels[$fd]->pop();
        }

        $result = $handle->write($data);

        if (!empty($result)) {
            Coroutine::create($callback, $result);
            $this->channels[$fd]->close();
        } else {
            $this->channels[$fd]->push($handle);
        }

        if (!empty($data)) {
            $this->recv($server, $fd, $data, $callback);
        }
    }

    public function onReceive(Server $server, $fd, $reactorId, $data)
    {
        $this->waitEvent('workerStart');

        $this->recv($server, $fd, $data, function ($data) use ($fd, $server) {
            $this->runInSandbox(function (Dispatcher $dispatcher) use ($fd, $data) {
                $dispatcher->dispatch($fd, $data);
            }, $fd, true);
        });
    }

    public function onClose(Server $server, int $fd, int $reactorId)
    {
        unset($this->channels[$fd]);
        $args = func_get_args();
        $this->runInSandbox(function (Event $event) use ($args) {
            $event->trigger("swoole.rpc.Close", $args);
        }, $fd);
    }
}
