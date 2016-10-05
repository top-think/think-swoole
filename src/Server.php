<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use swoole_http_server;
use swoole_server;
use swoole_websocket_server;

/**
 * Worker控制器扩展类
 */
abstract class Server
{
    protected $swoole;
    protected $serverType;
    protected $sockType;
    protected $mode;
    protected $host   = '0.0.0.0';
    protected $port   = 9501;
    protected $option = [];

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        // 实例化 Swoole 服务
        switch ($this->serverType) {
            case 'socket':
                $this->swoole = new swoole_websocket_server($this->host, $this->port);
                $eventList    = ['Open', 'Message', 'Close', 'HandShake'];
                break;
            case 'http':
                $this->swoole = new swoole_http_server($this->host, $this->port);
                $eventList    = ['Request'];
                break;
            default:
                $this->swoole = new swoole_server($this->host, $this->port, $this->mode, $this->sockType);
                $eventList    = ['Start', 'ManagerStart', 'ManagerStop', 'PipeMessage', 'Task', 'Packet', 'Finish', 'Receive', 'Connect', 'Close', 'Timer', 'WorkerStart', 'WorkerStop', 'Shutdown', 'WorkerError'];

        }
        // 设置参数
        if (!empty($this->option)) {
            $this->swoole->set($this->option);
        }
        // 初始化
        $this->init();

        // 设置回调
        foreach ($eventList as $event) {
            if (method_exists($this, 'on' . $event)) {
                $this->swoole->on($event, [$this, 'on' . $event]);
            }
        }
    }

    protected function init()
    {
    }

    public function start()
    {
        // Run worker
        $this->swoole->start();
    }

    public function stop()
    {
        $this->swoole->stop();
    }

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        call_user_func_array([$this->swoole, $method], $args);
    }
}
