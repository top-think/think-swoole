<?php

namespace think\swoole\rpc;

use Throwable;

class File extends \think\File
{
    protected $persistent = false;

    /**
     * 持久化该文件
     */
    public function persists()
    {
        $this->persistent = true;
    }

    public function __destruct()
    {
        //销毁时删除临时文件
        if ($this->persistent) {
            try {
                if (file_exists($this->getPathname())) {
                    unlink($this->getPathname());
                }
            } catch (Throwable $e) {

            }
        }
    }
}
