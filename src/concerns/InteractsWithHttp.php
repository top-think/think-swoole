<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Status;
use Swoole\Process;
use Symfony\Component\VarDumper\VarDumper;
use think\App;
use think\Container;
use think\Cookie;
use think\Event;
use think\exception\Handle;
use think\helper\Arr;
use think\Http;
use think\Middleware;
use think\swoole\middleware\ResetVarDumper;
use Throwable;
use function substr;

/**
 * Trait InteractsWithHttp
 * @package think\swoole\concerns
 * @property App $app
 * @property Container $container
 */
trait InteractsWithHttp
{
    use InteractsWithWebsocket;

    public function createHttpServer(Process\Pool $pool)
    {
        $this->setProcessName('http server process');

        $host = $this->getConfig('server.host');
        $port = $this->getConfig('server.port');

        $server = new Server($host, $port, false, true);

        Process::signal(SIGTERM, function () use ($pool, $server) {
            $server->shutdown();
            $pool->getProcess()->exit();
        });

        $server->handle('/', function (Request $req, Response $res) {
            $header = $req->header;
            if (Arr::get($header, 'connection') == 'upgrade' &&
                Arr::get($header, 'upgrade') == 'websocket' &&
                $this->wsEnable
            ) {
                $this->onHandShake($req, $res);
            } else {
                $this->onRequest($req, $res);
            }
        });

        $server->start();
    }

    protected function prepareHttp()
    {
        if ($this->getConfig('http.enable', false)) {

            $this->wsEnable = $this->getConfig('http.websocket.enable', false);

            if ($this->wsEnable) {
                $this->prepareWebsocket();
            }

            $this->addBatchWorker(swoole_cpu_num(), [$this, 'createHttpServer']);
        }
    }

    /**
     * "onRequest" listener.
     *
     * @param Request $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->runInSandbox(function (Http $http, Event $event, App $app, Middleware $middleware) use ($req, $res) {
            $request = $this->prepareRequest($req);

            //兼容var-dumper
            if (class_exists(VarDumper::class)) {
                $middleware->add(ResetVarDumper::class);
            }

            try {
                $response = $this->handleRequest($http, $request);
            } catch (Throwable $e) {
                $response = $this->app
                    ->make(Handle::class)
                    ->render($request, $e);
            }

            $this->sendResponse($res, $response, $app->cookie);
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
            $server['http_' . str_replace('-', '_', $key)] = $value;
        }

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \think\Request $request */
        $request = $this->app->make('request', [], true);

        return $request
            ->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withFiles($req->files ?: [])
            ->withInput($req->rawContent())
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
        $code = $response->getCode();
        $res->status($code, Status::getReasonPhrase($code));

        foreach ($cookie->getCookie() as $name => $val) {
            [$value, $expire, $option] = $val;

            $res->cookie($name, $value, $expire, $option['path'], $option['domain'], (bool) $option['secure'], (bool) $option['httponly'], $option['samesite']);
        }

        $content = $response->getContent();

        $this->sendByChunk($res, $content);
    }

    protected function sendByChunk(Response $res, $content)
    {
        $contentSize = strlen($content);
        $chunkSize   = 8192;

        if ($contentSize > $chunkSize) {
            $sendSize = 0;
            do {
                if (!$res->write(substr($content, $sendSize, $chunkSize))) {
                    break;
                }
            } while (($sendSize += $chunkSize) < $contentSize);
            $res->end();
        } else {
            $res->end($content);
        }
    }
}
