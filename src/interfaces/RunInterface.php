<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/12
 * Email:499873958@qq.com
 */

namespace think\swoole\interfaces;

interface RunInterface
{
    /**
     * 执行任务接口
     * @return mixed
     */
    public function run($server);

}
