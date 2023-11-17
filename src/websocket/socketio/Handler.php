<?php

namespace think\swoole\websocket\socketio;

use Exception;
use Swoole\Timer;
use Swoole\Websocket\Frame;
use think\Config;
use think\Event;
use think\Request;
use think\swoole\contract\websocket\HandlerInterface;
use think\swoole\Websocket;
use think\swoole\websocket\Event as WsEvent;

class Handler implements HandlerInterface
{
    /** @var Config */
    protected $config;

    protected $event;

    protected $websocket;

    protected $eio;

    protected $pingTimeoutTimer = 0;
    protected $pingIntervalTimer = 0;

    protected $pingInterval;
    protected $pingTimeout;

    public function __construct(Event $event, Config $config, Websocket $websocket)
    {
        $this->event        = $event;
        $this->config       = $config;
        $this->websocket    = $websocket;
        $this->pingInterval = $this->config->get('swoole.websocket.ping_interval', 25000);
        $this->pingTimeout  = $this->config->get('swoole.websocket.ping_timeout', 60000);
    }

    /**
     * "onOpen" listener.
     *
     * @param Request $request
     */
    public function onOpen(Request $request)
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
                $packet = Packet::fromString($enginePacket->data);
                switch ($packet->type) {
                    case Packet::CONNECT:
                        $this->onConnect($packet->data);
                        break;
                    case Packet::EVENT:
                        $type = array_shift($packet->data);
                        $data = $packet->data;
                        $result = $this->event->trigger('swoole.websocket.Event', new WsEvent($type, $data));

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
                        $this->websocket->close();
                        break;
                    default:
                        $this->websocket->close();
                        break;
                }
                break;
            case EnginePacket::PING:
                $this->event->trigger('swoole.websocket.Ping');
                $this->push(EnginePacket::pong($enginePacket->data));
                break;
            case EnginePacket::PONG:
                $this->event->trigger('swoole.websocket.Pong');
                $this->schedulePing();
                break;
            default:
                $this->websocket->close();
                break;
        }
    }

    /**
     * "onClose" listener.
     */
    public function onClose()
    {
        Timer::clear($this->pingTimeoutTimer);
        Timer::clear($this->pingIntervalTimer);
        $this->event->trigger('swoole.websocket.Close');
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
            $this->websocket->close();
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

    public function encodeMessage($message)
    {
        if ($message instanceof WsEvent) {
            $message = Packet::create(Packet::EVENT, [
                'data' => array_merge([$message->type], $message->data),
            ]);
        }

        if ($message instanceof Packet) {
            $message = EnginePacket::message($message->toString());
        }

        if ($message instanceof EnginePacket) {
            $message = $message->toString();
        }

        return $message;
    }

    protected function push($data)
    {
        $this->websocket->push($data);
    }

}
