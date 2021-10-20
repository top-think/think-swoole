<?php

namespace think\swoole\concerns;

use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\CloseFrame;
use Swoole\WebSocket\Frame;
use think\App;
use think\Container;
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

    /**
     * @var Channel[]
     */
    protected $wsMessageChannel = [];

    protected $wsEnable = false;

    /** @var Atomic */
    protected $wsIdAtomic;

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

            $websocket->setClient($res);

            $request = $this->prepareRequest($req);
            $request = $this->setRequestThroughMiddleware($app, $request);

            $fd = $this->wsIdAtomic->add();

            $this->wsMessageChannel[$fd] = new Channel(1);

            Coroutine::create(function () use ($res, $fd) {
                //推送消息
                while ($message = $this->wsMessageChannel[$fd]->pop()) {
                    $res->push($message);
                }
            });

            try {
                $id = "{$this->workerId}.{$fd}";

                $websocket->setSender($id);
                $websocket->join($id);

                $this->runWithBarrier(function () use ($request, $handler) {
                    $handler->onOpen($request);
                });

                $this->runWithBarrier(function () use ($handler, $res) {
                    $frame = null;
                    while (true) {
                        /** @var Frame|false|string $recv */
                        $recv = $res->recv();
                        if ($recv === '' || $recv === false || $recv instanceof CloseFrame) {
                            break;
                        }

                        if (empty($frame)) {
                            $frame         = new Frame();
                            $frame->opcode = $recv->opcode;
                            $frame->flags  = $recv->flags;
                            $frame->fd     = $recv->fd;
                            $frame->finish = false;
                        }

                        $frame->data .= $recv->data;
                        $frame->finish = $recv->finish;

                        if ($frame->finish) {
                            Coroutine::create([$handler, 'onMessage'], $frame);
                            $frame = null;
                        }
                    }
                });

                //关闭连接
                $res->close();
                $this->runWithBarrier(function () use ($handler) {
                    $handler->onClose();
                });
            } finally {
                // leave all rooms
                $websocket->leave();
                if (isset($this->wsMessageChannel[$fd])) {
                    $this->wsMessageChannel[$fd]->close();
                    unset($this->wsMessageChannel[$fd]);
                }
                $websocket->setClient(null);
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
        $this->prepareWebsocketIdAtomic();
        $this->prepareWebsocketRoom();

        $this->onEvent('message', function ($message) {
            if ($message instanceof PushMessage) {
                if (isset($this->wsMessageChannel[$message->fd])) {
                    $this->wsMessageChannel[$message->fd]->push($message->data);
                }
            }
        });

        $this->onEvent('workerStart', function () {
            $this->bindWebsocketRoom();
            $this->bindWebsocketHandler();
            $this->prepareWebsocketListener();
        });
    }

    protected function prepareWebsocketIdAtomic()
    {
        $this->wsIdAtomic = new Atomic();
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
