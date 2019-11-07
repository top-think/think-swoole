<?php

namespace think\swoole\rpc\client;

interface Connector
{
    public function sendAndRecv($data);
}
