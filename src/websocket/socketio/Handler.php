<?php

namespace think\swoole\websocket\socketio;

use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebsocketServer;
use think\Config;
use think\Request;
use think\swoole\contract\websocket\HandlerInterface;

class Handler implements HandlerInterface
{
    /** @var WebsocketServer */
    protected $server;

    /** @var Config */
    protected $config;

    public function __construct(Server $server, Config $config)
    {
        $this->server = $server;
        $this->config = $config;
    }

    /**
     * "onOpen" listener.
     *
     * @param int $fd
     * @param Request $request
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

            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $initPayload);
                $this->server->push($fd, $connectPayload);
            }
        }
    }

    /**
     * "onMessage" listener.
     *  only triggered when event handler not found
     *
     * @param Frame $frame
     * @return bool
     */
    public function onMessage(Frame $frame)
    {
        $packet = $frame->data;
        if (Packet::getPayload($packet)) {
            return false;
        }

        $this->checkHeartbeat($frame->fd, $packet);

        return true;
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

    protected function checkHeartbeat($fd, $packet)
    {
        $packetLength = strlen($packet);
        $payload      = '';

        if ($isPing = Packet::isSocketType($packet, 'ping')) {
            $payload .= Packet::PONG;
        }

        if ($isPing && $packetLength > 1) {
            $payload .= substr($packet, 1, $packetLength - 1);
        }

        if ($isPing && $this->server->isEstablished($fd)) {
            $this->server->push($fd, $payload);
        }
    }
}
