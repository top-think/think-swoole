<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole\facade;

use Swoole\Http\Request;
use Swoole\Http\Response;
use think\Facade;

/**
 * @see \think\swoole\Application
 * @mixin \think\swoole\Application
 * @method void initialize() static 初始化应用
 * @method void swoole(Request $request, Response $response) static 处理Swoole请求
 */
class Application extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return '\\think\\swoole\\Application';
    }
}
