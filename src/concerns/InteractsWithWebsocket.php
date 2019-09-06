<?php

namespace think\swoole\concerns;

use RuntimeException;
use Swoole\Http\Request;
use Swoole\Websocket\Frame;
use Swoole\Websocket\Server;
use think\App;
use think\Container;
use think\helper\Arr;
use think\swoole\facade\Server as SwooleServer;
use think\swoole\Sandbox;
use think\swoole\Websocket;
use think\swoole\websocket\HandlerContract;
use think\swoole\websocket\Parser;
use think\swoole\websocket\Pusher;
use think\swoole\websocket\room\RoomContract;
use think\swoole\websocket\room\TableRoom;
use think\swoole\websocket\socketio\Handler;
use think\swoole\websocket\socketio\Parser as SocketioParser;
use Throwable;

/**
 * Trait InteractsWithWebsocket
 * @package think\swoole\concerns
 *
 * @property App       $app
 * @property Container $container
 */
trait InteractsWithWebsocket
{
    /**
     * @var boolean
     */
    protected $isServerWebsocket = false;

    /**
     * @var HandlerContract
     */
    protected $websocketHandler;

    /**
     * @var Parser
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
            $websocket->reset(true)->setSender($req->fd);
            // set current request to sandbox
            $sandbox->setRequest($request);
            // enable sandbox
            $sandbox->init();

            // check if socket.io connection established
            if (!$this->websocketHandler->onOpen($req->fd, $request)) {
                return;
            }

            // trigger 'connect' websocket event
            if ($websocket->eventExists('connect')) {
                $websocket->call('connect', $request);
            }
        } catch (Throwable $e) {
            $this->logServerError($e);
        } finally {
            // disable and recycle sandbox resource
            $sandbox->clear();
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
        // execute parser strategies and skip non-message packet
        if ($this->payloadParser->execute($server, $frame)) {
            return;
        }

        /** @var Websocket $websocket */
        $websocket = $this->app->make(Websocket::class);
        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        try {
            // decode raw message via parser
            $payload = $this->payloadParser->decode($frame);

            $websocket->reset(true)->setSender($frame->fd);

            // enable sandbox
            $sandbox->init();

            // dispatch message to registered event callback
            ['event' => $event, 'data' => $data] = $payload;
            $websocket->eventExists($event)
                ? $websocket->call($event, $data)
                : $this->websocketHandler->onMessage($frame);
        } catch (Throwable $e) {
            $this->logServerError($e);
        } finally {
            // disable and recycle sandbox resource
            $sandbox->clear();
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

        try {
            $websocket->reset(true)->setSender($fd);
            // trigger 'disconnect' websocket event
            if ($websocket->eventExists('disconnect')) {
                $websocket->call('disconnect');
            } else {
                $this->websocketHandler->onClose($fd, $reactorId);
            }
            // leave all rooms
            $websocket->leave();
        } catch (Throwable $e) {
            $this->logServerError($e);
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
     * Set frame parser for websocket.
     *
     * @param Parser $payloadParser
     *
     * @return InteractsWithWebsocket
     */
    public function setPayloadParser(Parser $payloadParser)
    {
        $this->payloadParser = $payloadParser;

        return $this;
    }

    /**
     * Get frame parser for websocket.
     */
    public function getPayloadParser()
    {
        return $this->payloadParser;
    }

    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        $config      = $this->container->make('config');
        $isWebsocket = $config->get('swoole.websocket.enabled');
        $parser      = $config->get('swoole.websocket.parser', SocketioParser::class);

        if ($isWebsocket) {
            $this->events            = array_merge($this->events ?? [], $this->wsEvents);
            $this->isServerWebsocket = true;
            $this->setPayloadParser(new $parser);
        }
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
        return (bool) $this->container->make(SwooleServer::class)
                          ->connection_info($fd)['websocket_status'] ?? false;
    }

    /**
     * Prepare websocket handler for onOpen and onClose callback.
     *
     * @throws \Exception
     */
    protected function prepareWebsocketHandler()
    {
        $handlerClass = $this->container->make('config')->get('swoole.websocket.handler', Handler::class);

        if (!$handlerClass) {
            throw new RuntimeException('Websocket handler is not set in swoole.websocket config');
        }

        $this->setWebsocketHandler($this->app->make($handlerClass));
    }

    /**
     * Set websocket handler.
     *
     * @param HandlerContract $handler
     *
     * @return InteractsWithWebsocket
     */
    public function setWebsocketHandler(HandlerContract $handler)
    {
        $this->websocketHandler = $handler;

        return $this;
    }

    /**
     * Get websocket handler.
     *
     * @return HandlerContract
     */
    public function getWebsocketHandler(): HandlerContract
    {
        return $this->websocketHandler;
    }

    /**
     * @param string $class
     * @param array  $settings
     *
     * @return RoomContract
     */
    protected function createRoom(string $class, array $settings): RoomContract
    {
        return new $class($settings);
    }

    /**
     * Bind room instance to app container.
     */
    protected function bindRoom(): void
    {
        $this->app->bind(RoomContract::class, function (App $app) {
            $config = $app->make('config');
            $room   = $config->get('swoole.websocket.room', []);

            $className = Arr::pull($room, 'type', TableRoom::class);

            // create room instance and initialize
            $room = $this->createRoom($className, $room);
            $room->prepare();

            return $room;
        });

        $this->app->bind('swoole.room', RoomContract::class);
    }

    /**
     * Bind websocket instance to Laravel app container.
     */
    protected function bindWebsocket()
    {
        $this->app->bind(Websocket::class, function (App $app) {
            return new Websocket($app, $app->make(RoomContract::class));
        });

        $this->app->bind('swoole.websocket', Websocket::class);
    }

    /**
     * Load websocket routes file.
     */
    protected function loadWebsocketRoutes()
    {
        $routePath = $this->container->make('config')
            ->get('swoole.websocket.route_file');

        if (file_exists($routePath)) {
            return require $routePath;
        }
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
