<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use think\swoole\command\Queue;
use think\swoole\command\Rpc;
use think\swoole\command\RpcInterface;
use think\swoole\command\Server as ServerCommand;

class Service extends \think\Service
{

    public function boot()
    {
        $this->commands(
            ServerCommand::class,
            RpcInterface::class,
            Rpc::class,
            Queue::class
        );
    }

}
