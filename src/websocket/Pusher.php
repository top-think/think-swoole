<?php

namespace think\swoole\websocket;

use think\swoole\Manager;
use think\swoole\websocket\message\PushMessage;

/**
 * Class Pusher
 */
class Pusher
{

    /** @var Room */
    protected $room;

    /** @var Manager */
    protected $manager;

    protected $to    = [];
    protected $rooms = [];

    public function __construct(Manager $manager, Room $room)
    {
        $this->manager = $manager;
        $this->room    = $room;
    }

    public function to(...$values)
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->to(...$value);
            } elseif (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    public function room(...$values)
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->room(...$value);
            } elseif (!in_array($value, $this->to)) {
                $this->rooms[] = $value;
            }
        }

        return $this;
    }

    /**
     * Push message to related descriptors
     * @param $data
     * @return void
     */
    public function push($data): void
    {
        $fds = $this->to;

        foreach ($this->rooms as $room) {
            $clients = $this->room->getClients($room);
            if (!empty($clients)) {
                $fds = array_merge($fds, $clients);
            }
        }

        foreach (array_unique($fds) as $fd) {
            [$workerId, $fd] = explode('.', $fd);
            $this->manager->sendMessage($workerId, new PushMessage($fd, $data));
        }
    }

    public function emit(string $event, ...$data): void
    {
        $this->push([$event, ...$data]);
    }
}
