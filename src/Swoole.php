<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace think\swoole;

use Swoole\Http\Server as HttpServer;
use think\facade\Cache;

/**
 * Swoole 命令行服务类
 */
class Swoole extends Server
{
    protected $app;

    /**
     * 架构函数
     * @access public
     */
    public function __construct($host, $port)
    {
        $this->swoole = new HttpServer($this->host, $this->port);
    }

    public function option(array $option)
    {
        // 设置参数
        if (!empty($option)) {
            $this->swoole->set($option);
        }

        foreach ($this->event as $event) {
            // 自定义回调
            if (!empty($option[$event])) {
                $this->swoole->on($event, $option[$event]);
            } elseif (method_exists($this, 'on' . $event)) {
                $this->swoole->on($event, [$this, 'on' . $event]);
            }
        }

    }

    /**
     * 此事件在Worker进程/Task进程启动时发生,这里创建的对象可以在进程生命周期内使用
     *
     * @param $server
     * @param $worker_id
     */
    public function onWorkerStart($server, $worker_id)
    {
        // 应用实例化
        $this->app = new Application;

        // 应用初始化
        $this->app->initialize();

        // 获取管理进程pid
        Cache::set('swoole_pid', $server->manager_pid);
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response)
    {
        // 执行应用并响应
        $this->app->swoole($request, $response);
    }
}
