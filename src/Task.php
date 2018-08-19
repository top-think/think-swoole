<?php
/**
 * Created by PhpStorm.
 * User: xavier
 * Date: 2018/8/19
 * Time: 下午4:09
 */

namespace think\swoole;

use think\swoole\Application;

class Task
{
    public function __construct()
    {
    }

    /**
     * 异步投递任务
     * @param $task  任务，可以是闭包可以是任务模板
     * @param null $finishCallback 任务执行完成回调 可以为空
     * @param int $taskWorkerId 指定task worker 来执行任务，不指定，自动分配
     * @return bool
     */
    public function async($task, $finishCallback = null, $taskWorkerId = -1)
    {
        if ($task instanceof \Closure) {
            try {
                $task = new SuperClosure($task);
            } catch (\Throwable $throwable) {
                Trigger::throwable($throwable);
                return false;
            }
        }

        //var_dump(\think\swoole\Application::getServer());
        Application::getServer()->task($task, $taskWorkerId, $finishCallback);
    }
}