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

