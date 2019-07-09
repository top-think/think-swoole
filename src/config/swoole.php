<?php

use think\swoole\websocket\room\TableRoom;
use think\swoole\websocket\socketio\Handler;
use think\swoole\websocket\socketio\Parser;

return [
    'server'           => [
        'host'      => '0.0.0.0', // 监听地址
        'port'      => 80, // 监听端口
        'mode'      => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type' => SWOOLE_SOCK_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'options'   => [
            'pid_file'              => runtime_path() . 'swoole.pid',
            'log_file'              => runtime_path() . 'swoole.log',
            'daemonize'             => false,
            // Normally this value should be 1~4 times larger according to your cpu cores.
            'reactor_num'           => swoole_cpu_num(),
            'worker_num'            => swoole_cpu_num(),
            'task_worker_num'       => swoole_cpu_num(),
            'enable_static_handler' => true,
            'document_root'         => root_path('public'),
            'package_max_length'    => 20 * 1024 * 1024,
            'buffer_output_size'    => 10 * 1024 * 1024,
            'socket_buffer_size'    => 128 * 1024 * 1024,
            'max_request'           => 3000,
            'send_yield'            => true,
        ],
    ],
    'websocket'        => [
        'enabled'       => false,
        'handler'       => Handler::class,
        'parser'        => Parser::class,
        'route_file'    => base_path() . 'websocket.php',
        'ping_interval' => 25000,
        'ping_timeout'  => 60000,
        'room'          => [
            'type'        => TableRoom::class,
            'room_rows'   => 4096,
            'room_size'   => 2048,
            'client_rows' => 8192,
            'client_size' => 2048,
        ],
    ],
    'hot_update'       => [
        'enable'  => env('app_debug', false),
        'name'    => ['*.php'],
        'include' => [app_path()],
        'exclude' => [],
    ],
    'enable_coroutine' => true,
    'resetters'        => [],
    'tables'           => [],
];
