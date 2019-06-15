<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/8
 * Email:499873958@qq.com
 */

namespace think\swoole\interfaces;

interface TimerInterface extends RunInterface
{
    /**
     * 定时器时间间隔
     * @return mixed
     */
    public function getInterval();
}