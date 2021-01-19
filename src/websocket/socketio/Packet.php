<?php

namespace think\swoole\websocket\socketio;

/**
 * Class Packet
 */
class Packet
{
    /**
     * Socket.io packet type `open`.
     */
    const OPEN = 0;

    /**
     * Socket.io packet type `close`.
     */
    const CLOSE = 1;

    /**
     * Socket.io packet type `ping`.
     */
    const PING = 2;

    /**
     * Socket.io packet type `pong`.
     */
    const PONG = 3;

    /**
     * Socket.io packet type `message`.
     */
    const MESSAGE = 4;

    /**
     * Socket.io packet type 'upgrade'
     */
    const UPGRADE = 5;

    /**
     * Socket.io packet type `noop`.
     */
    const NOOP = 6;

    /**
     * Engine.io packet type `connect`.
     */
    const CONNECT = 0;

    /**
     * Engine.io packet type `disconnect`.
     */
    const DISCONNECT = 1;

    /**
     * Engine.io packet type `event`.
     */
    const EVENT = 2;

    /**
     * Engine.io packet type `ack`.
     */
    const ACK = 3;

    /**
     * Engine.io packet type `connect_error`.
     */
    const CONNECT_ERROR = 4;

    /**
     * Engine.io packet type 'binary event'
     */
    const BINARY_EVENT = 5;

    /**
     * Engine.io packet type `binary ack`. For acks with binary arguments.
     */
    const BINARY_ACK = 6;

    protected $packet = "";

    public function __construct(string $packet)
    {
        $this->packet = $packet;
    }

    public function getEngineType()
    {
        return $this->packet[0] ?? null;
    }

    public function getSocketType()
    {
        return $this->packet[1] ?? null;
    }

    public function getPayload()
    {
        return substr($this->packet, 1) ?: '';
    }
}
