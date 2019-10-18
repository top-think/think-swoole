<?php

namespace think\swoole\rpc\client;

use think\swoole\concerns\InteractsWithPoolConnector;

/**
 * Class Connection
 * @package think\swoole\rpc\client
 * @mixin Client
 */
class Connection
{
    use InteractsWithPoolConnector;
}
