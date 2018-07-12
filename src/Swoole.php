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
use Swoole\Table;
use think\Facade;

/**
 * Swoole 命令行服务类
 */
class Swoole extends Server
{
    protected $app;
    protected $appPath;
    protected $table;
    protected $fieldType = [
        'int'    => Table::TYPE_INT,
        'string' => Table::TYPE_STRING,
        'float'  => Table::TYPE_FLOAT,
    ];

    protected $fieldSize = [
        Table::TYPE_INT    => 4,
        Table::TYPE_STRING => 32,
        Table::TYPE_FLOAT  => 8,
    ];

    /**
     * 架构函数
     * @access public
     */
    public function __construct($host, $port, $ssl = false)
    {
        if ($ssl) {
            $this->swoole = new HttpServer($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        } else {
            $this->swoole = new HttpServer($host, $port);
        }
    }

    public function setAppPath($path)
    {
        $this->appPath = $path;
    }

    public function table(array $option)
    {
        $size        = !empty($option['size']) ? $option['size'] : 1024;
        $this->table = new Table($size);

        foreach ($option['column'] as $field => $type) {
            $length = null;

            if (is_array($type)) {
                list($type, $length) = $type;
            }

            if (isset($this->fieldType[$type])) {
                $type = $this->fieldType[$type];
            }

            $this->table->column($field, $type, isset($length) ? $length : $this->fieldSize[$type]);
        }

        $this->table->create();
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
        $this->app = new Application($this->appPath);

        if ($this->table) {
            $this->app['swoole_table'] = $this->table;
        }

        Facade::bind([
            'think\facade\Cookie'  => Cookie::class,
            'think\facade\Session' => Session::class,
        ]);

        // 应用初始化
        $this->app->initialize();

        $this->app->bindTo([
            'cookie'  => Cookie::class,
            'session' => Session::class,
        ]);
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
