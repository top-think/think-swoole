<?php

namespace think\swoole;

use Swoole\Server;
use Swoole\WebSocket\Frame;
use think\Event;
use think\Request;
use think\swoole\websocket\Pusher;
use think\swoole\websocket\Room;

/**
 * Class Websocket
 */
class Websocket
{
    /**
     * @var \think\App
     */
    protected $app;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Room
     */
    protected $room;

    /**
     * Scoket sender's fd.
     *
     * @var integer
     */
    protected $sender;

    /**
     * Recepient's fd or room name.
     *
     * @var array
     */
    protected $to = [];

    /**
     * Determine if to broadcast.
     *
     * @var boolean
     */
    protected $isBroadcast = false;

    /** @var Event */
    protected $event;

    /**
     * Websocket constructor.
     *
     * @param \think\App $app
     * @param Server $server
     * @param Room $room
     * @param Event $event
     */
    public function __construct(\think\App $app, Server $server, Room $room, Event $event)
    {
        $this->app    = $app;
        $this->server = $server;
        $this->room   = $room;
        $this->event  = $event;
    }

    /**
     * "onOpen" listener.
     *
     * @param int $fd
     * @param Request $request
     */
    public function onOpen($fd, Request $request)
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
     * @param int $reactorId
     */
    public function onClose($fd, $reactorId)
    {
        $this->event->trigger('swoole.websocket.Close', $reactorId);
    }

    /**
     * Set broadcast to true.
     */
    public function broadcast(): self
    {
        $this->isBroadcast = true;

        return $this;
    }

    /**
     * Get broadcast status value.
     */
    public function isBroadcast()
    {
        return $this->isBroadcast;
    }

    /**
     * Set multiple recipients fd or room names.
     *
     * @param integer|string|array
     *
     * @return $this
     */
    public function to($values): self
    {
        $values = is_string($values) || is_int($values) ? func_get_args() : $values;

        foreach ($values as $value) {
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * Get push destinations (fd or room name).
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Join sender to multiple rooms.
     *
     * @param string|integer|array $rooms
     *
     * @return $this
     */
    public function join($rooms): self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;

        $this->room->add($this->getSender(), $rooms);

        return $this;
    }

    /**
     * Make sender leave multiple rooms.
     *
     * @param array|string|integer $rooms
     *
     * @return $this
     */
    public function leave($rooms = []): self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;

        $this->room->delete($this->getSender(), $rooms);

        return $this;
    }

    public function push($data)
    {
        $fds      = $this->getFds();
        $assigned = !empty($this->getTo());

        try {
            if (empty($fds) && $assigned) {
                return false;
            }

            $job = new Job([Pusher::class, 'push'], [
                'sender'      => $this->getSender() ?: 0,
                'descriptors' => $fds,
                'broadcast'   => $this->isBroadcast(),
                'assigned'    => $assigned,
                'payload'     => $data,
            ]);

            if ($this->server->taskworker) {
                $result = $job->run($this->app);
            } else {
                $result = $this->server->task($job);
            }

            return $result !== false;
        } finally {
            $this->reset();
        }
    }

    public function emit(string $event, ...$data): bool
    {
        return $this->push($this->encode([
            'type' => $event,
            'data' => $data,
        ]));
    }

    protected function encode($packet)
    {
        return json_encode($packet);
    }

    protected function decode($payload)
    {
        $data = json_decode($payload, true);

        return [
            'type' => $data['type'] ?? null,
            'data' => $data['data'] ?? null,
        ];
    }

    /**
     * Close current connection.
     *
     * @param int|null $fd
     * @return boolean
     */
    public function close(int $fd = null)
    {
        return $this->server->close($fd ?: $this->getSender());
    }

    /**
     * @param int|null $fd
     * @return bool
     */
    public function isEstablished(int $fd = null): bool
    {
        return $this->server->isEstablished($fd ?: $this->getSender());
    }

    /**
     * @param int|null $fd
     * @param int $code
     * @param string $reason
     * @return bool
     */
    public function disconnect(int $fd = null, int $code = 1000, string $reason = ''): bool
    {
        return $this->server->disconnect($fd ?: $this->getSender(), $code, $reason);
    }

    /**
     * Set sender fd.
     *
     * @param integer
     *
     * @return $this
     */
    public function setSender(int $fd)
    {
        $this->sender = $fd;
        $this->reset();
        return $this;
    }

    /**
     * Get current sender fd.
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Get all fds we're going to push data to.
     */
    protected function getFds()
    {
        $to    = $this->getTo();
        $fds   = array_filter($to, function ($value) {
            return is_int($value);
        });
        $rooms = array_diff($to, $fds);

        foreach ($rooms as $room) {
            $clients = $this->room->getClients($room);
            // fallback fd with wrong type back to fds array
            if (empty($clients) && is_numeric($room)) {
                $fds[] = $room;
            } else {
                $fds = array_merge($fds, $clients);
            }
        }

        return array_values(array_unique($fds));
    }

    protected function reset()
    {
        $this->isBroadcast = false;
        $this->to          = [];
    }
}
