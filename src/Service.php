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

use think\Route;
use think\swoole\command\Rpc;
use think\swoole\command\RpcInterface;
use think\swoole\command\Server as ServerCommand;
use think\swoole\websocket\socketio\Controller;

class Service extends \think\Service
{

    public function boot()
    {
        $this->commands(ServerCommand::class, RpcInterface::class, Rpc::class);

        if ($this->app->config->get('swoole.websocket.enable', false)) {
            $this->registerRoutes(function (Route $route) {
                $route->group(function () use ($route) {
                    $route->get('socket.io/', '@upgrade');
                    $route->post('socket.io/', '@reject');
                })
                    ->prefix(Controller::class)
                    ->allowCrossDomain([
                        'Access-Control-Allow-Credentials' => 'true',
                        'X-XSS-Protection'                 => 0,
                    ]);
            });
        }
    }

}
