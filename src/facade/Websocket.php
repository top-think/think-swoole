<?php

namespace think\swoole\facade;

use think\Facade;

class Websocket extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.websocket';
    }
}
