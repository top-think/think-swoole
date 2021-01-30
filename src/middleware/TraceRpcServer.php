<?php

namespace think\swoole\middleware;

use Swoole\Coroutine;
use think\swoole\rpc\Protocol;
use think\tracing\Tracer;
use Throwable;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\ERROR;
use const OpenTracing\Tags\SPAN_KIND;
use const OpenTracing\Tags\SPAN_KIND_RPC_SERVER;

class TraceRpcServer
{
    protected $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function handle(Protocol $protocol, $next)
    {
        $context = $this->tracer->extract(TEXT_MAP, $protocol->getContext());
        $scope   = $this->tracer->startActiveSpan(
            'rpc.server:' . $protocol->getInterface() . '@' . $protocol->getMethod(),
            [
                'child_of' => $context,
                'tags'     => [
                    SPAN_KIND => SPAN_KIND_RPC_SERVER,
                ],
            ]
        );
        $span    = $scope->getSpan();

        try {
            return $next($protocol);
        } catch (Throwable $e) {
            $span->setTag(ERROR, $e);
            throw $e;
        } finally {
            $scope->close();

            Coroutine::defer(function () {
                $this->tracer->flush();
            });
        }
    }
}
