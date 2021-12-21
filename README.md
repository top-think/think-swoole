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

启动完成后，默认会在0.0.0.0:80启动一个HTTP Server，可以直接访问当前的应用。

swoole的相关参数可以在`config/swoole.php`里面配置（具体参考配置文件内容）。

如果需要使用守护进程方式运行，建议使用supervisor来管理进程

## 访问静态文件

1. 添加静态文件路由：

```php
Route::get('static/:path', function (string $path) {
    $filename = public_path() . $path;
    return download($filename)->force(false);
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
