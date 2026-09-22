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

## 协程使用注意

> Swoole 的协程是“单向”的：父协程退出后，子协程无法再确定自己属于哪个沙箱，父协程也不会等子协程。因此业务代码创建协程时，请使用 `think\swoole\Coroutine::create()` 而不是 `go()` / `Swoole\Coroutine::create()`。

### 沙箱归属与生命周期

- 经过 `think\swoole\Coroutine::create()` 创建的协程会自动继承当前协程的沙箱归属和作用域；
- 请求协程（sandbox root）会等待所有后代协程退出后才清理沙箱，等待期间沙箱保持有效，后台长任务不会被中断；
- 等待超过 `swoole.scope.wait_warning`（默认 30 秒）时只记录一条告警日志，用于排查一直没有退出的后代协程。

请求内需要延迟执行的任务，直接用协程实现（`Coroutine::sleep` 底层就是定时器）：

```php
use Swoole\Coroutine as Co;
use think\swoole\Coroutine;

Coroutine::create(function () use ($task) {
    Co::sleep(1.5); //sleep 由 Swoole\Coroutine 提供
    $task(); //继承请求的沙箱，请求协程会等待其执行完成
});
```

### 无归属协程

裸 `go()`、`Swoole\Timer` 回调等由 Swoole 直接创建的协程不经过沙箱入口，属于“无归属协程”：

- 这类协程本身不会被 root 等待（不计入作用域），请求结束后仍可能在运行；
- 归属和作用域只能沿父链回溯解析：父链上任一协程先退出，回溯即断，容器访问会抛出 `The app object has not been initialized`；
- `Swoole\Timer` 回调由事件循环直接创建，没有父链可回溯，完全无法关联。

`Swoole\Timer` 适合 worker 级的定时任务（心跳、清理等）。如果定时任务需要使用 `Db` / `Cache` / 模型等容器资源，可以在注册时捕获执行器，在回调里套一层独立沙箱执行：

```php
use Swoole\Timer;
use think\swoole\Manager;

//在请求（或其他沙箱）中注册定时器
$manager = app(Manager::class);

Timer::after(1000, function () use ($manager, $task) {
    //回调协程没有归属，套一层独立沙箱后即可正常使用容器
    $manager->runInSandbox(function () use ($task) {
        $task();
    });
});
```

独立沙箱是一个全新的应用环境，不包含注册时的请求上下文（request、鉴权、租户等），需要的数据请在注册时通过闭包显式传入；同理也不要捕获请求对象，避免延长其生命周期。

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
