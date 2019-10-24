<?php

namespace think\swoole\concerns;

use Swoole\Http\Request;
use Swoole\Server\Task;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use think\App;
use think\Container;
use think\Event;
use think\helper\Str;
use think\Pipeline;
use think\swoole\contract\websocket\HandlerInterface;
use think\swoole\contract\websocket\ParserInterface;
use think\swoole\contract\websocket\RoomInterface;
use think\swoole\Websocket;
use think\swoole\websocket\Pusher;
use think\swoole\websocket\Room;
use think\swoole\websocket\socketio\Handler;
use think\swoole\websocket\socketio\Parser as SocketioParser;

/**
 * Trait InteractsWithWebsocket
 * @package think\swoole\concerns
 *
 * @property App       $app
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
     * @param Server  $server
     * @param Request $req
     */
    public function onOpen($server, $req)
    {
        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        $websocket->setSender($req->fd);

        $this->runInSandbox(function (Event $event, HandlerInterface $handler, App $app) use ($req) {
            $request = $this->prepareRequest($req);
            $request = $this->setRequestThroughMiddleware($app, $request);

            if (!$handler->onOpen($req->fd, $request)) {
                $event->trigger("swoole.websocket.Connect", $request);
            }
        }, $req->fd, true);
    }

    /**
     * "onMessage" listener.
     *
     * @param Server $server
     * @param Frame  $frame
     */
    public function onMessage($server, $frame)
    {
        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        $websocket->setSender($frame->fd);

        $this->runInSandbox(function (Event $event, ParserInterface $parser, HandlerInterface $handler) use ($frame) {
            if (!$handler->onMessage($frame)) {
                $payload = $parser->decode($frame);

                ['event' => $name, 'data' => $data] = $payload;
                $name = Str::studly($name);
                if (!in_array($name, ['Close', 'Connect'])) {
                    $event->trigger("swoole.websocket." . $name, $data);
                }
            }
        }, $frame->fd, true);
    }

    /**
     * "onClose" listener.
     *
     * @param Server $server
     * @param int    $fd
     * @param int    $reactorId
     */
    public function onClose($server, $fd, $reactorId)
    {
        if (!$this->isWebsocketServer($fd) || !$server instanceof Server) {
            return;
        }

        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        $websocket->setSender($fd);

        $this->runInSandbox(function (Event $event, HandlerInterface $handler) use ($websocket, $fd, $reactorId) {
            try {
                if (!$handler->onClose($fd, $reactorId)) {
                    $event->trigger("swoole.websocket.Close");
                }
            } finally {
                // leave all rooms
                $websocket->leave();
            }
        }, $fd);
    }

    /**
     * @param App            $app
     * @param \think\Request $request
     * @return \think\Request
     */
    protected function setRequestThroughMiddleware(App $app, \think\Request $request)
    {
        $middleware = $this->getConfig('websocket.middleware', []);

        return (new Pipeline())
            ->send($request)
            ->through(array_map(function ($middleware) use ($app) {
                return function ($request, $next) use ($app, $middleware) {
                    if (is_array($middleware)) {
                        list($middleware, $param) = $middleware;
                    }
                    if (is_string($middleware)) {
                        $middleware = [$app->make($middleware), 'handle'];
                    }
                    return call_user_func($middleware, $request, $next, $param ?? null);
                };
            }, $middleware))
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
            $this->bindWebsocketParser();
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

        //消息推送任务
        $this->app->event->listen('swoole.task', function (Task $task, App $app) {
            if ($this->isWebsocketPushPayload($task->data)) {
                $pusher = $app->make(Pusher::class, $task->data['data']);
                $pusher->push();
            }
        });
    }

    /**
     * Prepare websocket handler for onOpen and onClose callback.
     *
     * @throws \Exception
     */
    protected function bindWebsocketHandler()
    {
        $handlerClass = $this->getConfig('websocket.handler', Handler::class);

        $this->app->bind(HandlerInterface::class, $handlerClass);

        $this->app->make(HandlerInterface::class);
    }

    protected function bindWebsocketParser()
    {
        $parserClass = $this->getConfig('websocket.parser', SocketioParser::class);

        $this->app->bind(ParserInterface::class, $parserClass);

        $this->app->make(ParserInterface::class);
    }

    /**
     * Bind room instance to app container.
     */
    protected function bindWebsocketRoom(): void
    {
        $this->app->instance(Room::class, $this->websocketRoom);
    }

    /**
     * Indicates if the payload is websocket push.
     *
     * @param mixed $payload
     *
     * @return boolean
     */
    public function isWebsocketPushPayload($payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        return $this->isWebsocketServer
            && ($payload['action'] ?? null) === Websocket::PUSH_ACTION
            && array_key_exists('data', $payload);
    }
}
