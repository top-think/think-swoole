<?php

use think\swoole\resetters\BindRequest;
use think\swoole\resetters\ClearInstances;
use think\swoole\resetters\RebindHttpContainer;
use think\swoole\resetters\ResetConfig;
use think\swoole\resetters\ResetSession;

return [
    'server'                => [
        'host'                => '0.0.0.0', // 监听地址
        'port'                => 9501, // 监听端口
        'mode'                => SWOOLE_PROCESS, // 运行模式 默认为SWOOLE_PROCESS
        'sock_type'           => SWOOLE_SOCK_TCP, // sock type 默认为SWOOLE_SOCK_TCP
        'app_path'            => '', // 应用地址 如果开启了 'daemonize'=>true 必须设置（使用绝对路径）
        'public_path'         => root_path('public'),
        'handle_static_files' => true,
        'options'             => [
            'pid_file'        => runtime_path() . 'swoole.pid',
            'log_file'        => runtime_path() . 'swoole.log',
            'task_worker_num' => 1,//swoole 任务工作进程数量
        ],
    ],
    'file_monitor'          => false, // 是否开启PHP文件更改监控（调试模式下自动开启）
    'file_monitor_interval' => 2, // 文件变化监控检测时间间隔（秒）
    'file_monitor_path'     => [], // 文件监控目录 默认监控application和config目录
    'resetters'             => [
        ResetConfig::class,
        ResetSession::class,
        ClearInstances::class,
        BindRequest::class,
        RebindHttpContainer::class,
    ],
    'tables'                => [

    ],
];
