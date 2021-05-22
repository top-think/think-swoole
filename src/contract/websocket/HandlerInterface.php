<?php

namespace think\swoole\contract\websocket;

use Swoole\WebSocket\Frame;
use think\Request;

interface HandlerInterface
{
    /**
     * "onOpen" listener.
     *
     * @param Request $request
     */
    public function onOpen(Request $request);

    /**
     * "onMessage" listener.
     *
     * @param Frame $frame
     */
    public function onMessage(Frame $frame);

    /**
     * "onClose" listener.
     */
    public function onClose();

    public function encodeMessage($message);

}
