<?php

namespace think\swoole\concerns;

use Swoole\Coroutine;
use think\helper\Arr;
use think\tracing\reporter\RedisReporter;
use think\tracing\Tracer;
use Throwable;

/**
 * 链路追踪共用上报进程
 */
trait InteractsWithTracing
{
    protected function prepareTracing()
    {
        if (class_exists(Tracer::class)) {
            $tracers = $this->container->config->get('tracing.tracers', []);
            $async   = [];

            foreach ($tracers as $name => $tracer) {
                if (Arr::get($tracer, 'async', false)) {
                    $async[] = $name;
                }
            }

            if (!empty($async)) {
                $this->addWorker(function () use ($async) {
                    $this->startTracingReporters($async);
                }, 'tracing reporter');

                $this->onEvent('workerStart', function () {
                    $this->bindTracingRedisReporter();
                });
            }
        }
    }

    protected function startTracingReporters(array $tracers)
    {
        foreach ($tracers as $name) {
            Coroutine::create(function () use ($name) {
                $this->reportTracer($name);
            });
        }
    }

    protected function reportTracer(string $name)
    {
        $tracer = $this->app->make(Tracer::class)->tracer($name);

        for (;;) {
            try {
                $tracer->report();
            } catch (Throwable $e) {
                $this->logServerError($e);
            }

            Coroutine::sleep(3);
        }
    }

    protected function bindTracingRedisReporter()
    {
        $this->getApplication()->bind(RedisReporter::class, function ($name) {
            $config = $this->container->config->get('tracing.redis', []);

            return new RedisReporter(
                $name,
                $this->getRedis()->connectionFor($config, 'tracing.redis')
            );
        });
    }
}
