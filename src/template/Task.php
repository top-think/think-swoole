<?php
namespace think\swoole\template;
/**
 * Created by PhpStorm.
 * User: xavier
 * Date: 2018/8/19
 * Time: 下午4:40
 */
abstract class Task
{
    protected $arg = null;

    public function __construct(...$arg)
    {
        $this->arg = $arg;
        $this->_initialize(...$arg);
    }

    abstract protected function _initialize(...$arg);

    abstract protected function run($serv, $task_id, $fromWorkerId);
}