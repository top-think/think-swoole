<?php

namespace think\swoole\concerns;

use RuntimeException;
use Swoole\Http\Request;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use think\App;
use think\Container;
use think\helper\Str;
use think\swoole\contract\websocket\HandlerInterface;
use think\swoole\contract\websocket\ParserInterface;
use think\swoole\contract\websocket\RoomInterface;
use think\swoole\Sandbox;
use think\swoole\Websocket;
use think\swoole\websocket\Pusher;
use think\swoole\websocket\Room;
use think\swoole\websocket\socketio\Handler;
use think\swoole\websocket\socketio\Parser as SocketioParser;
use Throwable;

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
    protected $isServerWebsocket = false;

    /**
     * @var HandlerInterface
     */
    protected $websocketHandler;

    /**
     * @var RoomInterface
     */
    protected $websocketRoom;

    /**
     * @var ParserInterface
     */
    protected $payloadParser;

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
        $request = $this->prepareRequest($req);
        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        try {
            $websocket->setSender($req->fd);
            $sandbox->init($req->fd);

            if (!$this->websocketHandler->onOpen($req->fd, $request)) {
                $sandbox->getApplication()->event->trigger("swoole.websocket.Connect", $request);
            }
        } catch (Throwable $e) {
            $this->logServerError($e);
        } finally {
            $sandbox->clear(false);
        }
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
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        try {
            $websocket->setSender($frame->fd);
            $sandbox->init($frame->fd);

            if (!$this->websocketHandler->onMessage($frame)) {
                $payload = $this->payloadParser->decode($frame);

                ['event' => $event, 'data' => $data] = $payload;
                $event = Str::studly($event);
                if (!in_array($event, ['Close', 'Connect'])) {
                    $sandbox->getApplication()->event->trigger("swoole.websocket." . $event, $data);
                }
            }
        } catch (Throwable $e) {
            $this->logServerError($e);
        } finally {
            $sandbox->clear(false);
        }
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
        if (!$this->isServerWebsocket($fd) || !$server instanceof Server) {
            return;
        }

        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        try {
            $websocket->setSender($fd);
            $sandbox->init($fd);

            if (!$this->websocketHandler->onClose($fd, $reactorId)) {
                $sandbox->getApplication()->event->trigger("swoole.websocket.Close");
            }

            // leave all rooms
            $websocket->leave();
        } catch (Throwable $e) {
            $this->logServerError($e);
        } finally {
            $sandbox->clear();
        }
    }

    /**
     * Push websocket message to clients.
     *
     * @param Server $server
     * @param mixed  $data
     */
    public function pushMessage($server, array $data)
    {
        $pusher = Pusher::make($data, $server);
        $pusher->push($this->payloadParser->encode(
            $pusher->getEvent(),
            $pusher->getMessage()
        ));
    }

    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        if (!$this->isServerWebsocket = $this->getConfig('websocket.enable', false)) {
            return;
        }

        $this->events = array_merge($this->events ?? [], $this->wsEvents);

        $parser = $this->getConfig('websocket.parser', SocketioParser::class);

        $this->payloadParser = new $parser;

        $this->prepareWebsocketRoom();
    }

    /**
     * Check if it's a websocket fd.
     *
     * @param int $fd
     *
     * @return bool
     */
    protected function isServerWebsocket(int $fd): bool
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
     * Prepare websocket handler for onOpen and onClose callback.
     *
     * @throws \Exception
     */
    protected function prepareWebsocketHandler()
    {
        $handlerClass = $this->getConfig('websocket.handler', Handler::class);

        if (!$handlerClass) {
            throw new RuntimeException('Websocket handler is not set in swoole.websocket config');
        }

        $this->websocketHandler = $this->app->make($handlerClass);
    }

    /**
     * Bind room instance to app container.
     */
    protected function bindRoom(): void
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

        return $this->isServerWebsocket
            && ($payload['action'] ?? null) === Websocket::PUSH_ACTION
            && array_key_exists('data', $payload);
    }
}
