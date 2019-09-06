<?php

namespace think\swoole;

use InvalidArgumentException;
use think\App;
use think\swoole\facade\Server;
use think\swoole\websocket\room\RoomContract;

/**
 * Class Websocket
 */
class Websocket
{

    const PUSH_ACTION   = 'push';
    const EVENT_CONNECT = 'connect';

    /**
     * Determine if to broadcast.
     *
     * @var boolean
     */
    protected $isBroadcast = false;

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
     * Websocket event callbacks.
     *
     * @var array
     */
    protected $callbacks = [];

    /**
     * Pipeline instance.
     *
     * @var App
     */
    protected $app;

    /**
     * Room adapter.
     *
     * @var RoomContract
     */
    protected $room;

    /**
     * Websocket constructor.
     *
     * @param App          $app
     * @param RoomContract $room
     */
    public function __construct(App $app, RoomContract $room)
    {
        $this->app  = $app;
        $this->room = $room;
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
     * Set multiple recipients fd or room names.
     *
     * @param integer, string, array
     *
     * @return $this
     */
    public function to($values): self
    {
        $values = is_string($values) || is_integer($values) ? func_get_args() : $values;

        foreach ($values as $value) {
            if (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * Join sender to multiple rooms.
     *
     * @param string, array $rooms
     *
     * @return $this
     */
    public function join($rooms): self
    {
        $rooms = is_string($rooms) || is_integer($rooms) ? func_get_args() : $rooms;

        $this->room->add($this->sender, $rooms);

        return $this;
    }

    /**
     * Make sender leave multiple rooms.
     *
     * @param array $rooms
     *
     * @return $this
     */
    public function leave($rooms = []): self
    {
        $rooms = is_string($rooms) || is_integer($rooms) ? func_get_args() : $rooms;

        $this->room->delete($this->sender, $rooms);

        return $this;
    }

    /**
     * Emit data and reset some status.
     *
     * @param string
     * @param mixed
     *
     * @return boolean
     */
    public function emit(string $event, $data): bool
    {
        $fds      = $this->getFds();
        $assigned = !empty($this->to);

        // if no fds are found, but rooms are assigned
        // that means trying to emit to a non-existing room
        // skip it directly instead of pushing to a task queue
        if (empty($fds) && $assigned) {
            return false;
        }

        $result = $this->app->make(Server::class)->task([
            'action' => static::PUSH_ACTION,
            'data'   => [
                'sender'    => $this->sender,
                'fds'       => $fds,
                'broadcast' => $this->isBroadcast,
                'assigned'  => $assigned,
                'event'     => $event,
                'message'   => $data,
            ],
        ]);

        $this->reset();

        return $result !== false;
    }

    /**
     * An alias of `join` function.
     *
     * @param string
     *
     * @return $this
     */
    public function in($room)
    {
        $this->join($room);

        return $this;
    }

    /**
     * Register an event name with a closure binding.
     *
     * @param string
     * @param callback
     *
     * @return $this
     */
    public function on(string $event, $callback)
    {
        if (!is_string($callback) && !is_callable($callback)) {
            throw new InvalidArgumentException(
                'Invalid websocket callback. Must be a string or callable.'
            );
        }

        $this->callbacks[$event] = $callback;

        return $this;
    }

    /**
     * Check if this event name exists.
     *
     * @param string
     *
     * @return boolean
     */
    public function eventExists(string $event)
    {
        return array_key_exists($event, $this->callbacks);
    }

    /**
     * Execute callback function by its event name.
     *
     * @param string
     * @param mixed
     *
     * @return mixed
     */
    public function call(string $event, $data = null)
    {
        if (!$this->eventExists($event)) {
            return;
        }

        // inject request param on connect event
        $isConnect = $event === static::EVENT_CONNECT;
        $dataKey   = $isConnect ? 'request' : 'data';

        $params = [
            'websocket' => $this,
            $dataKey    => $data,
        ];

        $callback = $this->callbacks[$event];

        if (is_string($callback) && strpos($callback, '@') !== false) {
            $segments = explode('@', $callback);
            return $this->app->invoke([$this->app->make($segments[0]), $segments[1]], $params);
        }

        return $this->app->invoke($callback, $params);
    }

    /**
     * Close current connection.
     *
     * @param integer
     *
     * @return boolean
     */
    public function close(int $fd = null)
    {
        return $this->app->make(Server::class)->close($fd ?: $this->sender);
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
     * Get broadcast status value.
     */
    public function getIsBroadcast()
    {
        return $this->isBroadcast;
    }

    /**
     * Get push destinations (fd or room name).
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Get all fds we're going to push data to.
     */
    protected function getFds()
    {
        $fds   = array_filter($this->to, function ($value) {
            return is_integer($value);
        });
        $rooms = array_diff($this->to, $fds);

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

    /**
     * Reset some data status.
     *
     * @param bool $force
     *
     * @return $this
     */
    public function reset($force = false)
    {
        $this->isBroadcast = false;
        $this->to          = [];

        if ($force) {
            $this->sender = null;
        }

        return $this;
    }

}
