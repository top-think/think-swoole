<?php

namespace think\swoole\websocket\socketio;

use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebsocketServer;
use think\App;
use think\Config;
use think\Request;
use think\swoole\facade\Server;
use think\swoole\websocket\HandlerContract;

class Handler implements HandlerContract
{
    /** @var WebsocketServer */
    protected $server;

    /** @var Config */
    protected $config;

    public function __construct(App $app)
    {
        $this->server = $app->make(Server::class);
        $this->config = $app->config;
    }

    /**
     * "onOpen" listener.
     *
     * @param int     $fd
     * @param Request $request
     *
     * @return bool
     */
    public function onOpen($fd, Request $request)
    {
        if (!$request->param('sid')) {
            $payload        = json_encode(
                [
                    'sid'          => base64_encode(uniqid()),
                    'upgrades'     => [],
                    'pingInterval' => $this->config->get('swoole.websocket.ping_interval'),
                    'pingTimeout'  => $this->config->get('swoole.websocket.ping_timeout'),
                ]
            );
            $initPayload    = Packet::OPEN . $payload;
            $connectPayload = Packet::MESSAGE . Packet::CONNECT;

            $this->server->push($fd, $initPayload);
            $this->server->push($fd, $connectPayload);

            return true;
        }

        return false;
    }

    /**
     * "onMessage" listener.
     *  only triggered when event handler not found
     *
     * @param Frame $frame
     */
    public function onMessage(Frame $frame)
    {
        return;
    }

    /**
     * "onClose" listener.
     *
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($fd, $reactorId)
    {
        return;
    }
}
