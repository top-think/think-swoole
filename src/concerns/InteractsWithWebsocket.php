<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Http\Server;
use Swoole\Event;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\CloseFrame;
use think\App;
use think\Container;
use think\helper\Arr;
use think\helper\Str;
use think\swoole\contract\websocket\HandlerInterface;
use think\swoole\contract\websocket\RoomInterface;
use think\swoole\Middleware;
use think\swoole\Websocket;
use think\swoole\websocket\message\PushMessage;
use think\swoole\websocket\Room;

/**
 * Trait InteractsWithWebsocket
 * @package think\swoole\concerns
 *
 * @property App $app
 * @property Container $container
 */
trait InteractsWithWebsocket
{

    /**
     * @var RoomInterface
     */
    protected $wsRoom;

    protected $wsMessages = [];

    /**
     * "onHandShake" listener.
     *
     * @param Request $req
     * @param Response $res
     */
    public function onHandShake($req, $res)
    {
        $this->runInSandbox(function (App $app, Websocket $websocket, HandlerInterface $handler) use ($req, $res) {
            $res->upgrade();

            $request = $this->prepareRequest($req);
            $request = $this->setRequestThroughMiddleware($app, $request);
            $closed  = false;

            Event::cycle(function () use ($handler, &$closed, $res, $req) {
                //推送消息
                if ($closed) {
                    unset($this->wsMessages[$req->fd]);
                    Event::cycle(null);
                }
                $messages = $this->wsMessages[$req->fd];
                if (!empty($messages)) {
                    unset($this->wsMessages[$req->fd]);
                    foreach ($messages as $message) {
                        $res->push($handler->encodeMessage($message));
                    }
                }
            });

            try {
                $fd = "{$this->workerId}.{$req->fd}";

                $websocket->setSender($fd);
                $handler->onOpen($request);

                while (true) {
                    $frame = $res->recv();
                    if ($frame === '' || $frame === false) {
                        break;
                    }

                    if ($frame->data == 'close' || get_class($frame) === CloseFrame::class) {
                        break;
                    }

                    $handler->onMessage($frame);
                }

                //关闭连接
                $res->close();
                $closed = true;

                $handler->onClose($req->fd);
            } finally {
                // leave all rooms
                $websocket->leave();
            }
        });
    }

    /**
     * @param App $app
     * @param \think\Request $request
     * @return \think\Request
     */
    protected function setRequestThroughMiddleware(App $app, \think\Request $request)
    {
        $app->instance('request', $request);
        return Middleware
            ::make($app, $this->getConfig('websocket.middleware', []))
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
        if ($this->getConfig('websocket.enable', false)) {
            $this->prepareWebsocketRoom();

            $this->onEvent('message', function ($message) {
                if ($message instanceof PushMessage) {
                    if (!isset($this->messages[$message->fd])) {
                        $this->wsMessages[$message->fd] = [];
                    }
                    $this->wsMessages[$message->fd][] = $message->data;
                }
            });

            $this->onEvent('workerStart', function (Server $server) {

                $this->bindWebsocketRoom();
                $this->bindWebsocketHandler();
                $this->prepareWebsocketListener();

                $server->handle('/', function (Request $req, Response $res) {
                    $header = $req->header;
                    if (Arr::get($header, 'connection') == 'upgrade' &&
                        Arr::get($header, 'upgrade') == 'websocket'
                    ) {
                        $this->onHandShake($req, $res);
                    } else {
                        $this->onRequest($req, $res);
                    }
                });
            });
        }
    }

    /**
     * Prepare websocket room.
     */
    protected function prepareWebsocketRoom()
    {
        $this->wsRoom = $this->container->make(Room::class);
        $this->wsRoom->prepare();
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
        $this->app->bind(HandlerInterface::class, $handlerClass);
    }

    /**
     * Bind room instance to app container.
     */
    protected function bindWebsocketRoom(): void
    {
        $this->app->instance(Room::class, $this->wsRoom);
    }

}
