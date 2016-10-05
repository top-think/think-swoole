ThinkPHP 5.0 Swoole 扩展
===============

## 安装
首先按照Swoole官网说明安装swoole扩展
然后使用
composer require topthink/think-swoole

## 使用方法
首先创建控制器类并继承 think\Swoole\Server，然后设置属性和添加回调方法

~~~
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

