<?php

namespace think\swoole\websocket;

use Swoole\WebSocket\Frame;
use think\Event;
use think\Request;
use think\swoole\contract\websocket\HandlerInterface;

class Handler implements HandlerInterface
{
    protected $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * "onOpen" listener.
     *
     * @param Request $request
     */
    public function onOpen(Request $request)
    {
        $this->event->trigger('swoole.websocket.Open', $request);
    }

    /**
     * "onMessage" listener.
     *
     * @param Frame $frame
     */
    public function onMessage(Frame $frame)
    {
        $this->event->trigger('swoole.websocket.Message', $frame);

        $this->event->trigger('swoole.websocket.Event', $this->decode($frame->data));
    }

    /**
     * "onClose" listener.
     *
     * @param int $fd
     */
    public function onClose($fd)
    {
        $this->event->trigger('swoole.websocket.Close');
    }

    protected function decode($payload)
    {
        $data = json_decode($payload, true);

        return [
            'type' => $data['type'] ?? null,
            'data' => $data['data'] ?? null,
        ];
    }

    public function encodeMessage($message)
    {
        if (is_array($message)) {
            $event = array_shift($message);
            return json_encode([
                'type' => $event,
                'data' => $message,
            ]);
        }
        return $message;
    }
}
