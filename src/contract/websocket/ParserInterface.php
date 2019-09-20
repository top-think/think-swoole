<?php

namespace think\swoole\contract\websocket;

use Swoole\WebSocket\Frame;

interface ParserInterface
{

    /**
     * Encode output payload for websocket push.
     *
     * @param string $event
     * @param mixed  $data
     *
     * @return mixed
     */
    public function encode(string $event, $data);

    /**
     * Input message on websocket connected.
     * Define and return event name and payload data here.
     *
     * @param Frame $frame
     *
     * @return array
     */
    public function decode($frame);
}
