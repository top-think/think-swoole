<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use Swoole\Http\Request;
use think\App;

/**
 * Swoole应用对象
 */
class Application extends App
{
    /**
     * 处理Swoole请求 在run方法之前调用
     * @access public
     * @param  \Swoole\Http\Request    $request
     * @param  $this
     */
    public function swoole(Request $request)
    {
        // 重置应用的开始时间和内存占用
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();

        // 销毁当前请求对象实例
        $this->delete('think\Request');

        // 重新实例化请求对象 处理swoole请求数据
        $this->request->withHeader($request->header)
            ->withServer($request->server)
            ->withGet($request->get)
            ->withPost($request->post)
            ->withCookie($request->cookie);

        $_COOKIE = $request->cookie;

        // 更新请求对象实例
        $this->route->setRequest($this->request);

        return $this;
    }
}
