<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

\think\Console::addDefaultCommands([
    '\\think\\swoole\\command\\Swoole',
    '\\think\\swoole\\command\\Server',
]);

\think\Facade::bind([
    \think\swoole\facade\Application::class => \think\swoole\Application::class,
    \think\swoole\facade\Swoole::class      => \think\swoole\Swoole::class,
]);
