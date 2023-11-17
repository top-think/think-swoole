<?php

namespace think\swoole\websocket;

class Event
{
    public $type;
    public $data;

    public function __construct($type, $data = null)
    {
        $this->type = $type;
        $this->data = $data;
    }
}
