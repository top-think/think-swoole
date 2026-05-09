<?php

namespace think\swoole\message;

class PushMessage
{
    public $fd;
    public $data;
    public $opcode = WEBSOCKET_OPCODE_TEXT;

    public function __construct($fd, $data, $opcode = WEBSOCKET_OPCODE_TEXT)
    {
        $this->fd     = $fd;
        $this->data   = $data;
        $this->opcode = $opcode;
    }
}
