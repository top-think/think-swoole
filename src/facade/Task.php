<?php

namespace think\swoole\facade;

use think\Facade;
use think\swoole\Task as TaskService;

/**
 * @see \think\swoole\Task
 * @mixin \think\swoole\Task
 * @method static string create(callable $callback, ?string $id = null)
 * @method static void run(string $id, callable $callback)
 * @method static bool isRunning(string $id)
 */
class Task extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeClass()
    {
        return TaskService::class;
    }
}
