<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use Swoole\Http\Request;
use Swoole\Http\Response;
use think\App;
use think\exception\HttpException;
use think\response\Redirect;

/**
 * Swoole应用对象
 */
class Application extends App
{
    /**
     * 处理Swoole请求
     * @access public
     * @param  \Swoole\Http\Request    $request
     * @param  \Swoole\Http\Response   $response
     * @param  void
     */
    public function swoole(Request $request, Response $response)
    {
        try {
            ob_start();

            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();

            // 销毁当前请求对象实例
            $this->delete('think\Request');

            // Session初始化
            $this->session->inited();

            // 重新实例化请求对象 处理swoole请求数据
            $this->request->withHeader($request->header)
                ->withServer($request->server)
                ->withGet($request->get ?: [])
                ->withPost($request->post ?: [])
                ->withCookie($request->cookie ?: [])
                ->setPathinfo(ltrim($request->server['path_info'], '/'));

            $_COOKIE = $request->cookie ?: $_COOKIE;

            // 更新请求对象实例
            $this->route->setRequest($this->request);

            $resp = $this->run();
            $resp->send();

            $content = ob_get_clean();
            $status  = $resp->getCode();

            if ($resp instanceof Redirect) {
                $response->redirect($resp->getTargetUrl(), $status);
            } else {
                $response->status($status);

                foreach ($resp->getHeader() as $key => $val) {
                    $response->header($key, $val);
                }

                $response->end($content);
            }
        } catch (HttpException $e) {
            $this->exception($response, $e, 404);
        } catch (\Exception $e) {
            $this->exception($response, $e, 500);
        } catch (\Throwable $e) {
            $this->exception($response, $e, 500);
        }
    }

    protected function exception($response, $e, $code)
    {
        $response->status($code);
        $response->end($e->getMessage());

        throw $e;
    }
}
