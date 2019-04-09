<?php

namespace think\swoole\facade;

use think\Facade;

class Room extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.room';
    }
}