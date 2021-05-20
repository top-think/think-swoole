<?php

namespace think\swoole;

use think\Event;
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
     * @var Manager
     */
    protected $manager;

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

    /** @var Event */
    protected $event;

    /**
     * Websocket constructor.
     *
     * @param \think\App $app
     * @param Room $room
     * @param Event $event
     */
    public function __construct(\think\App $app, Manager $manager, Room $room, Event $event)
    {
        $this->app     = $app;
        $this->room    = $room;
        $this->event   = $event;
        $this->manager = $manager;
    }

    protected function makePusher()
    {
        return new Pusher($this->manager, $this->room);
    }

    public function to(...$values)
    {
        return $this->makePusher()->to(...$values);
    }

    public function room(...$values)
    {
        return $this->makePusher()->room(...$values);
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
        $this->to($this->getSender())->push($data);
    }

    public function emit(string $event, ...$data): bool
    {
        return $this->push($this->encode([
            'type' => $event,
            'data' => $data,
        ]));
    }

    /**
     * Close current connection.
     *
     * @param string|null $fd
     * @return boolean
     */
    public function close($fd = null)
    {
        //todo
    }

    /**
     * Set sender fd.
     *
     * @param string
     *
     * @return $this
     */
    public function setSender($fd)
    {
        $this->sender = $fd;
        return $this;
    }

    /**
     * Get current sender fd.
     */
    public function getSender()
    {
        return $this->sender;
    }

}
