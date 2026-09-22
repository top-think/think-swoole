<?php

namespace think\swoole\coroutine;

use Swoole\Coroutine\Channel;

/**
 * 协程作用域
 *
 * 记录作用域内创建的后代协程数量，root 协程收尾时调用 wait() 挂起等待，
 * 后代协程全部退出前 root 协程不会结束，其上下文与沙箱资源也不会被提前销毁。
 */
class Scope
{
    /**
     * @var int 未退出的后代协程数量
     */
    protected $count = 0;

    /**
     * @var Channel|null 等待者信号，仅在 wait() 期间存在
     */
    protected $signal;

    /**
     * 登记一个后代协程
     */
    public function enter()
    {
        $this->count++;
    }

    /**
     * 后代协程退出
     */
    public function leave()
    {
        if ($this->count > 0 && --$this->count === 0 && $this->signal) {
            $this->signal->close();
        }
    }

    /**
     * 未退出的后代协程数量
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * 等待所有后代协程退出
     * @param float $timeout 最长等待时间(秒)，-1 为不限
     * @return bool 后代是否已全部退出
     */
    public function wait(float $timeout = -1)
    {
        if ($this->count > 0) {
            //每次等待使用独立信号，避免历史 close 影响本次等待
            $this->signal = $signal = new Channel(1);

            try {
                $signal->pop($timeout);
            } finally {
                $this->signal = null;
            }
        }

        return $this->count <= 0;
    }
}
