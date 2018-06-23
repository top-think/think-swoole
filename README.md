ThinkPHP 5.1 Swoole 扩展
===============

## 安装

首先按照Swoole官网说明安装swoole扩展
然后使用
composer require topthink/think-swoole

## 使用方法

### Server

首先创建控制器类并继承 think\Swoole\Server，然后设置属性和添加回调方法

~~~
<?php
namespace app\index\controller;

use think\Swoole\Server;

class Swoole extends Server
{
	protected $host = '127.0.0.1';
	protected $port = 9502;
	protected $option = [ 
		'worker_num'	=> 4,
		'daemonize'	=> true,
		'backlog'	=> 128
	];

	public function onReceive($server, $fd, $from_id, $data)
	{
		$server->send($fd, 'Swoole: '.$data);
	}
}
~~~

支持swoole所有的回调方法定义（回调方法必须是public类型）
serverType 属性定义为 socket/http 则支持swoole的swoole_websocket_server和swoole_http_server

在命令行启动服务端
~~~
php index.php index/Swoole/start
~~~

### HttpServer

在应用根目录下创建 server.php 文件

~~~
<?php
// 加载框架基础文件
require __DIR__ . '/thinkphp/base.php';

use think\Swoole\Server;

class Swoole extends Server
{
	protected $host = '127.0.0.1';
	protected $port = 9502;
	protected $option = [ 
		'worker_num'	=> 4,
		'enable_static_handler'	=> true,
		'document_root'         => "/var/www/tp.com/public",
	];
	protected $app;

    /**
     * 此事件在Worker进程/Task进程启动时发生,这里创建的对象可以在进程生命周期内使用
     *
     * @param $server
     * @param $worker_id
     */
    public function onWorkerStart($server, $worker_id)
    {
        // 应用实例化
        $this->app = new think\swoole\Application;

        // 应用初始化
        $this->app->initialize();
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest($request, $response)
    {
        // 执行应用并响应
        try {
            ob_start();

            $this->app
                ->swoole($request)
                ->run()
                ->send();

            $content = ob_get_clean();

            $response->end($content);
        } catch (\Exception $e) {
            $response->status(500);
            $response->end($e->getMessage());

            throw $e;
        }
    }
}
(new Swoole())->start();
~~~

命令行下启动服务端
~~~
php server.php
~~~

