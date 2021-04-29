<?php

namespace think\swoole\rpc;

use Throwable;

class File extends \think\File
{
    public function __destruct()
    {
        //销毁时删除临时文件
        try {
            if (file_exists($this->getPathname())) {
                unlink($this->getPathname());
            }
        } catch (Throwable $e) {

        }
    }
}
