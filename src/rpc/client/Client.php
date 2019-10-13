<?php

namespace think\swoole\rpc\client;

use think\swoole\concerns\InteractsWithPoolConnector;

/**
 * Class Client
 * @package think\swoole\rpc\client
 * @mixin \Swoole\Coroutine\Client
 */
class Client
{
    use InteractsWithPoolConnector;
}
