<?php

namespace think\swoole\concerns;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use think\App;
use think\Container;
use think\Cookie;
use think\Event;
use think\exception\Handle;
use think\Http;
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
        $args = func_get_args();
        $this->runInSandbox(function (Http $http, Cookie $cookie, Event $event) use ($args, $req, $res) {
            $event->trigger('swoole.request', $args);

            $request = $this->prepareRequest($req);
            try {
                $response = $this->handleRequest($http, $request);
                $this->sendResponse($res, $response, $cookie);
            } catch (Throwable $e) {
                $response = $this->app
                    ->make(Handle::class)
                    ->render($request, $e);

                $this->sendResponse($res, $response, $cookie);
            }
        });
    }

    protected function handleRequest(Http $http, $request)
    {
        $level = ob_get_level();
        ob_start();

        $response = $http->run($request);

        $content = $response->getContent();

        if (ob_get_level() == 0) {
            ob_start();
        }

        $http->end($response);

        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return $response;
    }

    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];

        foreach ($header as $key => $value) {
            $server["http_" . str_replace('-', '_', $key)] = $value;
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

    protected function sendResponse(Response $res, \think\Response $response, Cookie $cookie)
    {
        // 发送Header
        foreach ($response->getHeader() as $key => $val) {
            $res->header($key, $val);
        }

        // 发送状态码
        $res->status($response->getCode());

        foreach ($cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $res->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }

        $content = $response->getContent();

        $res->end($content);
    }
}
