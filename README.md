ThinkPHP Swoole 扩展
===============

交流群：787100169 [![点击加群](https://pub.idqqimg.com/wpa/images/group.png "点击加群")](https://jq.qq.com/?_wv=1027&k=VRcdnUKL)

## 安装

首先按照Swoole官网说明安装swoole扩展，然后使用

~~~
composer require topthink/think-swoole
~~~

安装swoole扩展。

## 使用方法

直接在命令行下启动HTTP服务端。

~~~
php think swoole
~~~

启动完成后，默认会在0.0.0.0:8080启动一个HTTP Server，可以直接访问当前的应用。

swoole的相关参数可以在`config/swoole.php`里面配置（具体参考配置文件内容）。

如果需要使用守护进程方式运行，建议使用supervisor来管理进程

## 访问静态文件
> 4.0开始协程风格服务端默认不支持静态文件访问，建议使用nginx来支持静态文件访问，也可使用路由输出文件内容，下面是示例，可参照修改
1. 添加静态文件路由：

```php
Route::get('static/:path', function (string $path) {
    $filename = public_path() . $path;
    return new \think\swoole\response\File($filename);
})->pattern(['path' => '.*\.\w+$']);
```

2. 访问路由 `http://localhost/static/文件路径`

## 队列支持

> 4.0开始协程风格服务端没有task进程了，使用think-queue代替

使用方法见 [think-queue](https://github.com/top-think/think-queue)

以下配置代替think-queue里的最后一步:`监听任务并执行`,无需另外起进程执行队列

```php
return [
    // ...
    'queue'      => [
        'enable'  => true,
        //键名是队列名称
        'workers' => [
            //下面参数是不设置时的默认配置
            'default'            => [
                'delay'      => 0,
                'sleep'      => 3,
                'tries'      => 0,
                'timeout'    => 60,
                'worker_num' => 1,
            ],
            //使用@符号后面可指定队列使用驱动
            'default@connection' => [
                //此处可不设置任何参数，使用上面的默认配置
            ],
        ],
    ],
    // ...
];

```

### websocket

> 新增路由调度的方式，方便实现多个websocket服务

#### 配置

```
swoole.websocket.route = true 时开启
```

#### 路由定义
```php
Route::get('path1','controller/action1');
Route::get('path2','controller/action2');
```

#### 控制器

```php
use \think\swoole\Websocket;
use \think\swoole\websocket\Event;
use \Swoole\WebSocket\Frame;
use \think\swoole\websocket\Room;

class Controller {

    public function action1(){//不可以在这里注入websocket对象
    
        return \think\swoole\helper\websocket()
            ->onOpen(...)
            ->onMessage(function(Websocket $websocket, Frame $frame){ //只可在事件响应这里注入websocket对象
                //...
                $websocket->join('room_key'); //将当前连接加入到某个room，后续可以向该room发送消息 这个room里的都可以收到
                //比如room_key可以直接使用这个用户的id，然后其他地方需要给某个用户发送消息，直接向这个room发送消息即可
                //...
                $websocket->push('message'); //给当前连接发送消息
                //...
                $websocket->emit('event_name', 'message'); //给当前连接发送事件
                //...
                $websocket->to('room_key')->push('message'); //给指定room的所有连接发送消息 在http请求的控制器中也可以注入Websocket对象这样发消息
                //...
            })
            ->onClose(...);
    }
    
    public function action2(){
    
        return \think\swoole\helper\websocket()
            ->onOpen(...)
            ->onMessage(function(Websocket $websocket, Frame $frame){
               //...
            })
            ->onClose(...);
    }
}
```

### 流式输出

```php

class Controller {

    public function action(){
        return \think\swoole\helper\iterator(value(function(){
            foreach(range(1,10) as $i)
                yield $i;
                sleep(1);//模拟等待
            }
        }));
    }
}
```
