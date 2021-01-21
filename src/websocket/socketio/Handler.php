<?php

namespace think\swoole\websocket\socketio;

use Exception;
use Swoole\Server;
use Swoole\Websocket\Frame;
use think\Config;
use think\Event;
use think\Request;
use think\swoole\Websocket;
use think\swoole\websocket\Room;

class Handler extends Websocket
{
    /** @var Config */
    protected $config;

    protected $eio;

    public function __construct(Server $server, Room $room, Event $event, Config $config)
    {
        $this->config = $config;
        parent::__construct($server, $room, $event);
    }

    /**
     * "onOpen" listener.
     *
     * @param int $fd
     * @param Request $request
     */
    public function onOpen($fd, Request $request)
    {
        $this->eio = $request->param('EIO');

        $payload     = json_encode(
            [
                'sid'          => base64_encode(uniqid()),
                'upgrades'     => [],
                'pingInterval' => $this->config->get('swoole.websocket.ping_interval'),
                'pingTimeout'  => $this->config->get('swoole.websocket.ping_timeout'),
            ]
        );
        $initPayload = Packet::OPEN . $payload;

        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, $initPayload);
        }
        if ($this->eio < 4) {
            $this->onConnect($fd);
        }
    }

    protected function onConnect($fd, $data = null)
    {
        try {
            $this->event->trigger('swoole.websocket.Connect', $data);
            $payload = Packet::MESSAGE . Packet::CONNECT;
            if ($this->eio >= 4) {
                $payload .= json_encode(['sid' => base64_encode(uniqid())]);
            }
        } catch (Exception $exception) {
            $payload = Packet::MESSAGE . Packet::CONNECT_ERROR . json_encode(['message' => $exception->getMessage()]);
        }
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, $payload);
        }
    }

    /**
     * "onMessage" listener.
     *
     * @param Frame $frame
     * @return bool
     */
    public function onMessage(Frame $frame)
    {
        $packet = new Packet($frame->data);

        switch ($packet->getEngineType()) {
            case Packet::MESSAGE:
                $payload = substr($packet->getPayload(), 1);
                switch ($packet->getSocketType()) {
                    case Packet::CONNECT:
                        $this->onConnect($frame->fd, $payload);
                        break;
                    case Packet::EVENT:
                    case Packet::ACK:
                        $start = strpos($payload, '[');

                        if ($start > 0) {
                            $id      = substr($payload, 0, $start);
                            $payload = substr($payload, $start);
                        }

                        $result = $this->event->trigger('swoole.websocket.Event', $this->decode($payload));

                        if (isset($id)) {
                            $this->server->push($frame->fd, $this->pack(Packet::ACK . $id, end($result)));
                        }
                        break;
                    case Packet::DISCONNECT:
                        $this->event->trigger('swoole.websocket.Disconnect');
                        break;
                }
                break;
            case Packet::PING:
                if ($this->server->isEstablished($frame->fd)) {
                    $this->server->push($frame->fd, Packet::PONG . $packet->getPayload());
                }
                break;
        }

        return true;
    }

    protected function decode($payload)
    {
        $data = json_decode($payload, true);

        return [
            'type' => $data[0],
            'data' => $data[1] ?? null,
        ];
    }

    protected function pack($type, ...$args)
    {
        $packet = Packet::MESSAGE . $type;

        $data = implode(",", array_map(function ($arg) {
            return json_encode($arg);
        }, $args));

        return "{$packet}[{$data}]";
    }

    protected function encode(string $event, $data)
    {
        return $this->pack(Packet::EVENT, $event, $data);
    }
}
