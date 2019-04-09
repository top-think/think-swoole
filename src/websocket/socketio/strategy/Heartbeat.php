<?php

namespace think\swoole\websocket\socketio\strategy;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use think\swoole\websocket\socketio\Packet;

class Heartbeat
{
    /**
     * If return value is true will skip decoding.
     *
     * @param Server $server
     * @param Frame  $frame
     *
     * @return boolean
     */
    public function handle($server, $frame)
    {
        $packet       = $frame->data;
        $packetLength = strlen($packet);
        $payload      = '';

        if (Packet::getPayload($packet)) {
            return false;
        }

        if ($isPing = Packet::isSocketType($packet, 'ping')) {
            $payload .= Packet::PONG;
        }

        if ($isPing && $packetLength > 1) {
            $payload .= substr($packet, 1, $packetLength - 1);
        }

        if ($isPing) {
            $server->push($frame->fd, $payload);
        }

        return true;
    }
}
