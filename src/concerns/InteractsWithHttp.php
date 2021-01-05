<?php

namespace think\swoole\concerns;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;
use Symfony\Component\VarDumper\VarDumper;
use think\App;
use think\Container;
use think\Cookie;
use think\Event;
use think\exception\Handle;
use think\Http;
use think\Middleware;
use think\swoole\middleware\ResetVarDumper;
use Throwable;

/**
 * Trait InteractsWithHttp
 * @package think\swoole\concerns
 * @property App $app
 * @property Container $container
 * @method Server getServer()
 */
trait InteractsWithHttp
{
    public static $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Too Early',                                                   // RFC-ietf-httpbis-replay-04
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        449 => 'Retry With',
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',                                     // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];

    /**
     * "onRequest" listener.
     *
     * @param Request $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->waitCoordinator('workerStart');

        $args = func_get_args();
        $this->runInSandbox(function (Http $http, Event $event, App $app, Middleware $middleware) use ($args, $req, $res) {
            $event->trigger('swoole.request', $args);

            //兼容var-dumper
            if (class_exists(VarDumper::class)) {
                $middleware->add(ResetVarDumper::class);
            }

            $request = $this->prepareRequest($req);
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
        $res->status($code, isset(self::$statusTexts[$code]) ? self::$statusTexts[$code] : 'unknown status');

        foreach ($cookie->getCookie() as $name => $val) {
            [$value, $expire, $option] = $val;

            $res->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false, $option['samesite']);
        }

        $content = $response->getContent();

        $this->sendByChunk($res, $content);
    }

    protected function sendByChunk(Response $res, $content)
    {
        $contentSize = \strlen($content);
        $chunkSize   = 8192;

        if ($contentSize > $chunkSize) {
            $sendSize = 0;
            do {
                if (!$res->write(\substr($content, $sendSize, $chunkSize))) {
                    break;
                }
            } while (($sendSize += $chunkSize) < $contentSize);
            $res->end();
        } else {
            $res->end($content);
        }
    }
}
