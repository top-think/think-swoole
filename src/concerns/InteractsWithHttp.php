<?php

namespace think\swoole\concerns;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use think\App;
use think\Container;
use think\exception\Handle;
use think\swoole\Sandbox;
use Throwable;

/**
 * Trait InteractsWithHttp
 * @package think\swoole\concerns
 * @property App       $app
 * @property Container $container
 * @method Server getServer()
 */
trait InteractsWithHttp
{

    /**
     * "onRequest" listener.
     *
     * @param Request  $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->app->event->trigger('swoole.request', func_get_args());

        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);

        $request = $this->prepareRequest($req);

        try {
            $sandbox->init();

            $response = $sandbox->run($request);

            $this->sendResponse($sandbox, $response, $res);
        } catch (Throwable $e) {
            try {
                $exceptionResponse = $this->app
                    ->make(Handle::class)
                    ->render($request, $e);

                $this->sendResponse($sandbox, $exceptionResponse, $res);
            } catch (Throwable $e) {
                $this->logServerError($e);
            }
        } finally {
            $sandbox->clear();
        }
    }

    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];

        if (isset($header['x-requested-with'])) {
            $server['HTTP_X_REQUESTED_WITH'] = $header['x-requested-with'];
        }

        if (isset($header['referer'])) {
            $server['http_referer'] = $header['referer'];
        }

        if (isset($header['host'])) {
            $server['http_host'] = $header['host'];
        }

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \think\Request $request */
        $request = $this->app->make('request', [], true);

        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . (!empty($req->server['query_string']) ? '?' . $req->server['query_string'] : ''))
            ->setPathinfo(ltrim($req->server['path_info'], '/'));
    }

    protected function sendResponse(Sandbox $sandbox, \think\Response $thinkResponse, \Swoole\Http\Response $swooleResponse)
    {
        // 发送Header
        foreach ($thinkResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }

        // 发送状态码
        $swooleResponse->status($thinkResponse->getCode());

        foreach ($sandbox->getApplication()->cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $swooleResponse->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }

        $content = $thinkResponse->getContent();

        if (!empty($content)) {
            $swooleResponse->write($content);
        }

        $swooleResponse->end();
    }
}
