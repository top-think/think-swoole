<?php

namespace think\swoole\websocket\socketio;

use Exception;
use Swoole\Server;
use Swoole\Timer;
use Swoole\Websocket\Frame;
use think\App;
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

    protected $pingTimeoutTimer  = null;
    protected $pingIntervalTimer = null;

    protected $pingInterval;
    protected $pingTimeout;

    public function __construct(App $app, Server $server, Room $room, Event $event, Config $config)
    {
        $this->config       = $config;
        $this->pingInterval = $this->config->get('swoole.websocket.ping_interval', 25000);
        $this->pingTimeout  = $this->config->get('swoole.websocket.ping_timeout', 60000);
        parent::__construct($app, $server, $room, $event);
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

        $payload = json_encode(
            [
                'sid'          => base64_encode(uniqid()),
                'upgrades'     => [],
                'pingInterval' => $this->pingInterval,
                'pingTimeout'  => $this->pingTimeout,
            ]
        );

        $this->push(EnginePacket::open($payload));

        $this->event->trigger('swoole.websocket.Open', $request);

        if ($this->eio < 4) {
            $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);
            $this->onConnect();
        } else {
            $this->schedulePing();
        }
    }

    /**
     * "onMessage" listener.
     *
     * @param Frame $frame
     */
    public function onMessage(Frame $frame)
    {
        $enginePacket = EnginePacket::fromString($frame->data);

        $this->event->trigger('swoole.websocket.Message', $enginePacket);

        $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);

        switch ($enginePacket->type) {
            case EnginePacket::MESSAGE:
                $packet = $this->decode($enginePacket->data);
                switch ($packet->type) {
                    case Packet::CONNECT:
                        $this->onConnect($packet->data);
                        break;
                    case Packet::EVENT:
                        $type   = array_shift($packet->data);
                        $data   = $packet->data;
                        $result = $this->event->trigger('swoole.websocket.Event', ['type' => $type, 'data' => $data]);

                        if ($packet->id !== null) {
                            $responsePacket = Packet::create(Packet::ACK, [
                                'id'   => $packet->id,
                                'nsp'  => $packet->nsp,
                                'data' => $result,
                            ]);

                            $this->push($responsePacket);
                        }
                        break;
                    case Packet::DISCONNECT:
                        $this->event->trigger('swoole.websocket.Disconnect');
                        $this->close();
                        break;
                    default:
                        $this->close();
                        break;
                }
                break;
            case EnginePacket::PING:
                $this->push(EnginePacket::pong($enginePacket->data));
                break;
            case EnginePacket::PONG:
                $this->schedulePing();
                break;
            default:
                $this->close();
                break;
        }
    }

    /**
     * "onClose" listener.
     *
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($fd, $reactorId)
    {
        Timer::clear($this->pingTimeoutTimer);
        Timer::clear($this->pingIntervalTimer);
        $this->event->trigger('swoole.websocket.Close', $reactorId);
    }

    protected function onConnect($data = null)
    {
        try {
            $this->event->trigger('swoole.websocket.Connect', $data);
            $packet = Packet::create(Packet::CONNECT);
            if ($this->eio >= 4) {
                $packet->data = ['sid' => base64_encode(uniqid())];
            }
        } catch (Exception $exception) {
            $packet = Packet::create(Packet::CONNECT_ERROR, [
                'data' => ['message' => $exception->getMessage()],
            ]);
        }

        $this->push($packet);
    }

    protected function resetPingTimeout($timeout)
    {
        Timer::clear($this->pingTimeoutTimer);
        $this->pingTimeoutTimer = Timer::after($timeout, function () {
            $this->close();
        });
    }

    protected function schedulePing()
    {
        Timer::clear($this->pingIntervalTimer);
        $this->pingIntervalTimer = Timer::after($this->pingInterval, function () {
            $this->push(EnginePacket::ping());
            $this->resetPingTimeout($this->pingTimeout);
        });
    }

    protected function encode($packet)
    {
        return Parser::encode($packet);
    }

    protected function decode($payload)
    {
        return Parser::decode($payload);
    }

    public function push($data)
    {
        if ($data instanceof Packet) {
            $data = EnginePacket::message($this->encode($data));
        }
        if ($data instanceof EnginePacket) {
            $data = $data->toString();
        }
        return parent::push($data);
    }

    public function emit(string $event, ...$data): bool
    {
        $packet = Packet::create(Packet::EVENT, [
            'data' => array_merge([$event], $data),
        ]);
        return $this->push($packet);
    }
}
