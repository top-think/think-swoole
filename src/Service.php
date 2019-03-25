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

use Swoole\Http\Server as HttpServer;
use Swoole\Websocket\Server as WebsocketServer;
use think\swoole\command\Server as ServerCommand;
use think\swoole\facade\Server;

class Service extends \think\Service
{
    protected $isWebsocket = false;

    /**
     * @var HttpServer | WebsocketServer
     */
    protected static $server;

    public function register()
    {
        $this->isWebsocket = $this->app->config->get('swoole.websocket.enabled', false);

        $this->app->bind(Server::class, function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }

            return static::$server;
        });

        $this->app->bind('swoole.server', Server::class);
    }

    public function boot()
    {
        $this->commands([
            'swoole' => ServerCommand::class,
        ]);
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $server     = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        $config     = $this->app->config;
        $host       = $config->get('swoole.server.host');
        $port       = $config->get('swoole.server.port');
        $socketType = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode       = $config->get('swoole.server.mode', SWOOLE_PROCESS);

        static::$server = new $server($host, $port, $mode, $socketType);

        $options = $config->get('swoole.server.options');

        static::$server->set($options);
    }
}