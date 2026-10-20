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

### Redis 连接共享

IPC、WebSocket Room、可靠任务和异步链路追踪统一使用 `swoole.redis` 中定义的命名连接。同一个 Worker 内，相同连接名只创建一个连接池：

```php
return [
    'redis' => [
        'default'     => 'default',
        'connections' => [
            'default' => [
                'host'                => '127.0.0.1',
                'port'                => 6379,
                'password'            => '',
                'database'            => 0,
                'timeout'             => 10,
                'max_active'          => 10,
                'max_wait_time'       => 5,
                'max_idle_time'       => 20,
                'idle_check_interval' => 10,
            ],
        ],
    ],
];
```

模块默认使用 `default` 连接，也可以指定其他连接名：

```php
return [
    'ipc' => [
        'redis' => [
            'connection' => 'default',
            'prefix'     => 'swoole:ipc:',
        ],
    ],
    'websocket' => [
        'room' => [
            'redis' => [
                'connection' => 'default',
                'prefix'     => 'swoole:room:',
            ],
        ],
    ],
    'task' => [
        'redis' => [
            'connection' => 'default',
            'prefix'     => 'swoole:task:',
        ],
    ],
];
```

异步链路追踪在 `tracing.redis` 中选择连接，它的 key 前缀由 Tracing 自身维护。所有 `async` tracer 共用一个上报 Worker，每个 tracer 使用独立协程消费自己的队列：

```php
return [
    'redis' => [
        'connection' => 'default',
    ],
];
```

业务代码可以通过服务或门面使用共享连接池：

```php
use think\swoole\facade\Redis;

$value = Redis::connection()->run(function (\Redis $client) {
    return $client->get('key');
});

Redis::connection()->set('key', 'value');

// SUBSCRIBE、MONITOR 等阻塞或独占命令
Redis::connection()->dedicated(function (\Redis $client) {
    // ...
});
```

`run()` 会在回调结束后归还连接，事务和管道应完整写在回调中。阻塞命令必须使用 `dedicated()`。旧模块内联的 `host`、`port` 等配置仍兼容，并会注册为独立的 `legacy.*` 连接；迁移到 `connection` 配置后才会与其他模块共用连接池。

### Redis 分布式锁

Lock 默认使用 Worker 内的 Table 驱动，也可以切换为 Redis 让不同节点共享同一把锁：

```php
return [
    'lock' => [
        'enable' => true,
        'type'   => 'redis',
        'redis'  => [
            'connection' => 'default',
            'prefix'     => 'swoole:lock:',
        ],
    ],
];
```

```php
use think\swoole\Lock;

$lock = app(Lock::class);
$lock->lock('order:' . $orderId, 30);

try {
    // 临界区必须短于 30 秒
} finally {
    $lock->unlock('order:' . $orderId);
}
```

Table 和 Redis 驱动都会为每次获取保存协程级 owner token，释放时校验所有权，避免误删其他协程持有的锁。Redis 锁使用 `SET NX EX` 自动过期，并通过 Lua compare-and-delete 释放。锁固定 TTL，不会自动续期，也不支持重入。Redis Lock 依赖 Think-Swoole 的协程环境，不支持普通非协程 PHP 进程。

### 任务存活心跳

需要跨进程判断子协程是否仍在执行时，可以使用 `Task` 服务。框架只在 Redis 中维护一个带 TTL 的心跳 key，不保存任务业务状态：

```php
use think\swoole\facade\Task;

$taskId = Task::create(function () use ($task) {
    $task();
});

if (!Task::isRunning($taskId)) {
    // 最近一个租约周期内没有心跳，任务已结束或执行进程已失联
}
```

任务可以使用框架生成的任务 ID，也可以传入唯一业务 ID：

```php
Task::create($callback, 'order:' . $orderId);
```

需要同步阻塞当前协程执行时使用 `run()`，任务 ID 必须由业务提供：

```php
Task::run('order:' . $orderId, function () use ($orderId) {
    // 在当前协程中同步执行
});
```

默认每 5 秒续租、租约 15 秒，可通过 `swoole.task` 配置：

```php
return [
    'task' => [
        'heartbeat_interval' => 5,
        'lease_ttl'          => 15,
        'redis'              => [
            'connection' => 'default',
            'prefix'     => 'swoole:task:',
        ],
    ],
];
```

`create()` 创建子协程并立即返回任务 ID；`run()` 直接阻塞当前协程，直到 callback 完成。任务正常结束时会立即删除心跳 key；进程崩溃时心跳停止，key 在 `lease_ttl` 后自动过期。两种情况下 `isRunning()` 最终都会返回 `false`，调用方再结合任务自身写入的业务状态判断完成、失败或中断。

任务异常会直接写入应用错误日志，不额外保存异常状态。框架不会自动重试；业务确认任务中断后需要自行重新提交，并保证副作用幂等。

可靠任务仍通过 `Coroutine::create()` 在当前 Sandbox 中运行，因此请求会等待任务结束。任务 ID 需要在启动后持久化，才能在进程崩溃后继续查询。

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

#### 静态推送

在 HTTP 控制器、事件监听器或其他业务代码中，可以直接使用 `Pusher` 门面向指定房间推送消息，无需注入 `Websocket`：

```php
use think\swoole\facade\Pusher;

// 向单个房间推送消息
Pusher::to('room_key')->push('message');

// 同时向多个房间推送
Pusher::to('room_a', 'room_b')->push('message');
Pusher::to(['room_a', 'room_b'])->push('message');

// 向房间推送事件
Pusher::to('user_1001')->emit('notice', ['content' => 'message']);
```

> 每次静态调用都会创建一个独立的 `Pusher` 实例，上一次 `to()` 设置的目标不会影响下一次推送。

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

也可以直接传入一个文件或其他可读的流资源：

```php
public function download()
{
    $stream = fopen(runtime_path() . 'large-file.zip', 'rb');

    return \think\swoole\helper\stream($stream)
        ->header([
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="large-file.zip"',
        ]);
}
```

流响应默认每 8192 字节读取一次，并在发送完成后关闭传入的资源。如果资源由其他代码管理，可以将第三个参数设为 `false`：

```php
\think\swoole\helper\stream($stream, 8192, false);
```

对于服务器上的本地文件，应优先使用 `\think\swoole\helper\file()` 或 `download()`，它们会使用 Swoole 的 `sendfile`，并支持 Range、ETag 和 Last-Modified。
