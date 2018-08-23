<?php
/**
 * Created by PhpStorm.
 * User: xavier
 * Date: 2018/8/19
 * Time: 下午4:40
 */

namespace think\swoole\template;

abstract class Timer
{
    protected $arg  = null;
    protected $lock = false;

    public function __construct(...$arg)
    {
        $this->arg = $arg;
        $this->_initialize(...$arg);
    }

    abstract protected function _initialize(...$arg);

    abstract protected function run();
}
