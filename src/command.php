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

namespace think;

// 注册命令行指令
Console::addDefaultCommands([
    '\\think\\swoole\\command\\Swoole',
    '\\think\\swoole\\command\\Server',
]);

// 绑定Facade
Facade::bind([
    swoole\facade\Application::class => swoole\Application::class,
    swoole\facade\Http::class        => swoole\Http::class,
]);

// 指定日志类驱动
Loader::addClassMap([
    'think\\log\\driver\\File' => __DIR__ . '/log/File.php',
]);
