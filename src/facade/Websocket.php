<?php

namespace think\swoole\facade;

use think\Facade;

/**
 * Class Websocket
 * @package think\swoole\facade
 * @mixin \think\swoole\Websocket
 */
class Websocket extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.websocket';
    }
}
