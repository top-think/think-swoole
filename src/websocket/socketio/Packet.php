<?php

namespace think\swoole\websocket\socketio;

/**
 * Class Packet
 */
class Packet
{
    /**
     * Socket.io packet type `connect`.
     */
    const CONNECT = 0;

    /**
     * Socket.io packet type `disconnect`.
     */
    const DISCONNECT = 1;

    /**
     * Socket.io packet type `event`.
     */
    const EVENT = 2;

    /**
     * Socket.io packet type `ack`.
     */
    const ACK = 3;

    /**
     * Socket.io packet type `connect_error`.
     */
    const CONNECT_ERROR = 4;

    /**
     * Socket.io packet type 'binary event'
     */
    const BINARY_EVENT = 5;

    /**
     * Socket.io packet type `binary ack`. For acks with binary arguments.
     */
    const BINARY_ACK = 6;

    public $type;
    public $nsp  = '/';
    public $data = null;
    public $id   = null;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public static function create($type, array $decoded = [])
    {
        $new     = new static($type);
        $new->id = $decoded['id'] ?? null;
        if (isset($decoded['nsp'])) {
            $new->nsp = $decoded['nsp'] ?: '/';
        } else {
            $new->nsp = '/';
        }
        $new->data = $decoded['data'] ?? null;
        return $new;
    }
}
