<?php

namespace think\swoole;

use Swoole\Server;
use think\swoole\contract\websocket\ParserInterface;
use think\swoole\coroutine\Context;
use think\swoole\websocket\Room;

/**
 * Class Websocket
 */
class Websocket
{

    const PUSH_ACTION   = 'push';
    const EVENT_CONNECT = 'connect';

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Room
     */
    protected $room;

    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * Websocket constructor.
     *
     * @param Server $server
     * @param Room $room
     * @param ParserInterface $parser
     */
    public function __construct(Server $server, Room $room, ParserInterface $parser)
    {
        $this->server = $server;
        $this->room   = $room;
        $this->parser = $parser;
    }

    /**
     * Set broadcast to true.
     */
    public function broadcast(): self
    {
        Context::setData('websocket._broadcast', true);

        return $this;
    }

    /**
     * Get broadcast status value.
     */
    public function isBroadcast()
    {
        return Context::getData('websocket._broadcast', false);
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

        $to = Context::getData('websocket._to', []);

        foreach ($values as $value) {
            if (!in_array($value, $to)) {
                $to[] = $value;
            }
        }

        Context::setData('websocket._to', $to);

        return $this;
    }

    /**
     * Get push destinations (fd or room name).
     */
    public function getTo()
    {
        return Context::getData('websocket._to', []);
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

        $this->room->add($this->getSender(), $rooms);

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

        $this->room->delete($this->getSender(), $rooms);

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
    public function emit(string $event, $data = null): bool
    {
        $fds      = $this->getFds();
        $assigned = !empty($this->getTo());

        try {
            if (empty($fds) && $assigned) {
                return false;
            }

            $result = $this->server->task([
                'action' => static::PUSH_ACTION,
                'data'   => [
                    'sender'      => $this->getSender() ?: 0,
                    'descriptors' => $fds,
                    'broadcast'   => $this->isBroadcast(),
                    'assigned'    => $assigned,
                    'payload'     => $this->parser->encode($event, $data),
                ],
            ]);

            return $result !== false;
        } finally {
            $this->reset();
        }
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
        Context::setData('websocket._sender', $fd);

        return $this;
    }

    /**
     * Get current sender fd.
     */
    public function getSender()
    {
        return Context::getData('websocket._sender');
    }

    /**
     * Get all fds we're going to push data to.
     */
    protected function getFds()
    {
        $to    = $this->getTo();
        $fds   = array_filter($to, function ($value) {
            return is_integer($value);
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
        Context::removeData('websocket._to');
        Context::removeData('websocket._broadcast');
    }
}
