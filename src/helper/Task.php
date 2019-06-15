<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/8
 * Email:499873958@qq.com
 */

namespace think\swoole\helper;

use think\App;
use think\swoole\facade\Server;

class Task
{
    private $app;
    
    public function __construct(App $app)
    {
        $this->app = $app;
    }
    
    /**
     * 异步投递任务
     * @param mixed $task 任务，可以是闭包可以是任务模板
     * @param mixed $finishCallback 任务执行完成回调 可以为空
     * @param int $taskWorkerId 指定task worker 来执行任务，不指定，自动分配
     * @return mixed
     */
    public function async($task, $finishCallback = null, $taskWorkerId = -1)
    {
        if ($task instanceof \Closure) {
            try {
                $task = new SuperClosure($task);
            } catch (\Throwable $throwable) {
                return false;
            }
        }
        return $this->app->make(Server::class)->task($task, $taskWorkerId, $finishCallback);
    }
}
