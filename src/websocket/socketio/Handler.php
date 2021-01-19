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
                switch ($packet->getSocketType()) {
                    case Packet::CONNECT:
                        try {
                            $this->event->trigger('swoole.websocket.Connect');
                            $payload = Packet::MESSAGE . Packet::CONNECT;
                            if ($this->eio >= 4) {
                                $payload .= json_encode(['sid' => base64_encode(uniqid())]);
                            }
                        } catch (Exception $exception) {
                            $payload = sprintf(Packet::MESSAGE . Packet::CONNECT_ERROR . '"%s"', $exception->getMessage());
                        }
                        if ($this->server->isEstablished($frame->fd)) {
                            $this->server->push($frame->fd, $payload);
                        }
                        break;
                    case Packet::EVENT:
                        $payload = substr($packet->getPayload(), 1);
                        $this->event->trigger('swoole.websocket.Event', $this->decode($payload));
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

    /**
     * "onClose" listener.
     *
     * @param int $fd
     * @param int $reactorId
     * @return bool
     */
    public function onClose($fd, $reactorId)
    {

    }

    protected function decode($payload)
    {
        $data = json_decode($payload, true);

        return [
            'type' => $data[0],
            'data' => $data[1] ?? null,
        ];
    }

    protected function encode(string $event, $data)
    {
        $packet       = Packet::MESSAGE . Packet::EVENT;
        $shouldEncode = is_array($data) || is_object($data);
        $data         = $shouldEncode ? json_encode($data) : $data;
        $format       = $shouldEncode ? '["%s",%s]' : '["%s","%s"]';

        return $packet . sprintf($format, $event, $data);
    }
}
