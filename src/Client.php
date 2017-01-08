<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 刘志淳 <chun@engineer.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use swoole_client;

/**
 * Swoole 客户端控制器扩展类
 */
class Client
{
    protected $swoole;
    protected $sockType = SWOOLE_SOCK_TCP;
    protected $isSync = SWOOLE_SOCK_SYNC;
    protected $host = '0.0.0.0';
    protected $port = 9501;
    protected $timeout = 0.1;
    protected $flag = 0;
    protected $option = [];
    protected $eventList = [''];

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        // 实例化 Swoole 客户端
        $this->swoole = new swoole_client($this->sockType, $this->isSync);

        // 设置参数
        if (!empty($this->option)) {
            $this->swoole->set($this->option);
        }

        // 异步客户端注册回调函数
        if (SWOOLE_SOCK_SYNC != $this->isSync) {
            $eventList = array_merge(['Connect', 'Error', 'Receive', 'Close'], $this->eventList);

            foreach ($eventList as $event) {
                if (method_exists($this, 'on' . $event)) {
                    $this->swoole->on($event, [$this, 'on' . $event]);
                }
            }
        }
    }

    /**
     * 连接到远程服务器
     *
     * @param string $host 是远程服务器的地址 v1.6.10+ 支持填写域名 Swoole会自动进行DNS查询
     * @param int $port 是远程服务器端口
     * @param float $timeout 是网络IO的超时，单位是s，支持浮点数。默认为0.1s，即100ms
     * @param int $flag 参数在UDP类型时表示是否启用udp_connect。设定此选项后将绑定$host与$port，此UDP将会丢弃非指定host/port的数据包。
     * 在send/recv前必须使用swoole_client_select来检测是否完成了连接
     * @return bool
     */
    public function connect($host = '0.0.0.0', $port = 9501, $timeout = 0.1, $flag = 0)
    {
        if (SWOOLE_SOCK_SYNC != $this->isSync) {
            $this->swoole->connect($this->host, $this->port, $this->timeout, $this->flag);
        } else {
            return $this->swoole->connect($host, $port, $timeout, $flag);
        }
    }

    /**
     * 向远程服务器发送数据
     *
     * 参数为字符串，支持二进制数据。
     * 成功发送返回的已发数据长度
     * 失败返回false，并设置$swoole_client->errCode
     *
     * @param string $data
     * @return bool
     */
    public function send($data)
    {
        return $this->swoole->send($data);
    }

    /**
     * 从服务器端接收数据
     *
     * 如果设定了$waitall就必须设定准确的$size，否则会一直等待，直到接收的数据长度达到$size
     * 如果设置了错误的$size，会导致recv超时，返回 false
     * 调用成功返回结果字符串，失败返回 false，并设置$swoole_client->errCode属性
     *
     * @param int $size 接收数据的最大长度
     * @param bool $waitAll 是否等待所有数据到达后返回
     * @return string
     */
    public function recv($size = 65535, $waitAll = false)
    {
        return $this->swoole->recv($size, $waitAll);
    }

    /**
     * 返回错误码
     * @return mixed
     */
    public function getErrCode()
    {
        return $this->swoole->errCode;
    }

    /**
     * 返回将错误码转换后的错误信息
     * @return string
     */
    public function getError()
    {
        return socket_strerror($this->swoole->errCode);
    }

    /**
     * 关闭远程连接
     * swoole_client对象在析构时会自动close
     * @return bool
     */
    public function close()
    {
        return $this->swoole->close();
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
