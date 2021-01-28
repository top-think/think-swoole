<?php

namespace think\swoole\concerns;

use Swoole\Http\Request;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use think\App;
use think\Container;
use think\helper\Str;
use think\swoole\contract\websocket\RoomInterface;
use think\swoole\Middleware;
use think\swoole\Websocket;
use think\swoole\websocket\Room;

/**
 * Trait InteractsWithWebsocket
 * @package think\swoole\concerns
 *
 * @property App $app
 * @property Container $container
 * @method \Swoole\Server getServer()
 */
trait InteractsWithWebsocket
{
    /**
     * @var boolean
     */
    protected $isWebsocketServer = false;

    /**
     * @var RoomInterface
     */
    protected $websocketRoom;

    /**
     * Websocket server events.
     *
     * @var array
     */
    protected $wsEvents = ['open', 'message', 'close'];

    /**
     * "onOpen" listener.
     *
     * @param Server $server
     * @param Request $req
     */
    public function onOpen($server, $req)
    {
        $this->waitCoordinator('workerStart');

        $this->runInSandbox(function (App $app, Websocket $websocket) use ($req) {
            $request = $this->prepareRequest($req);
            $app->instance('request', $request);
            $request = $this->setRequestThroughMiddleware($app, $request);
            $websocket->setSender($req->fd);
            $websocket->onOpen($req->fd, $request);
        }, $req->fd, true);
    }

    /**
     * "onMessage" listener.
     *
     * @param Server $server
     * @param Frame $frame
     */
    public function onMessage($server, $frame)
    {
        $this->runInSandbox(function (Websocket $websocket) use ($frame) {
            $websocket->setSender($frame->fd);
            $websocket->onMessage($frame);
        }, $frame->fd, true);
    }

    /**
     * "onClose" listener.
     *
     * @param Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($server, $fd, $reactorId)
    {
        if (!$server instanceof Server || !$this->isWebsocketServer($fd)) {
            return;
        }

        $this->runInSandbox(function (Websocket $websocket) use ($fd, $reactorId) {
            $websocket->setSender($fd);
            try {
                $websocket->onClose($fd, $reactorId);
            } finally {
                // leave all rooms
                $websocket->leave();
            }
        }, $fd);
    }

    /**
     * @param App $app
     * @param \think\Request $request
     * @return \think\Request
     */
    protected function setRequestThroughMiddleware(App $app, \think\Request $request)
    {
        return Middleware::make($app, $this->getConfig('websocket.middleware', []))
            ->pipeline()
            ->send($request)
            ->then(function ($request) {
                return $request;
            });
    }

    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        if (!$this->isWebsocketServer = $this->getConfig('websocket.enable', false)) {
            return;
        }

        $this->events = array_merge($this->events ?? [], $this->wsEvents);

        $this->prepareWebsocketRoom();

        $this->onEvent('workerStart', function () {
            $this->bindWebsocketRoom();
            $this->bindWebsocketHandler();
            $this->prepareWebsocketListener();
        });
    }

    /**
     * Check if it's a websocket fd.
     *
     * @param int $fd
     *
     * @return bool
     */
    protected function isWebsocketServer(int $fd): bool
    {
        return $this->getServer()->getClientInfo($fd)['websocket_status'] ?? false;
    }

    /**
     * Prepare websocket room.
     */
    protected function prepareWebsocketRoom()
    {
        // create room instance and initialize
        $this->websocketRoom = $this->container->make(Room::class);
        $this->websocketRoom->prepare();
    }

    protected function prepareWebsocketListener()
    {
        $listeners = $this->getConfig('websocket.listen', []);

        foreach ($listeners as $event => $listener) {
            $this->app->event->listen('swoole.websocket.' . Str::studly($event), $listener);
        }

        $subscribers = $this->getConfig('websocket.subscribe', []);

        foreach ($subscribers as $subscriber) {
            $this->app->event->observe($subscriber, 'swoole.websocket.');
        }
    }

    /**
     * Prepare websocket handler for onOpen and onClose callback
     */
    protected function bindWebsocketHandler()
    {
        $handlerClass = $this->getConfig('websocket.handler');
        if ($handlerClass && is_subclass_of($handlerClass, Websocket::class)) {
            $this->app->bind(Websocket::class, $handlerClass);
        }
    }

    /**
     * Bind room instance to app container.
     */
    protected function bindWebsocketRoom(): void
    {
        $this->app->instance(Room::class, $this->websocketRoom);
    }

}
