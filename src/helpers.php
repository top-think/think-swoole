<?php

if (!function_exists('swoole_cpu_num')) {
    function swoole_cpu_num(): int
    {
        return 2;
    }
}

if (!defined('SWOOLE_SOCK_TCP')) {
    define('SWOOLE_SOCK_TCP', 1);
}

if (!defined('SWOOLE_PROCESS')) {
    define('SWOOLE_PROCESS', 3);
}
