<?php

namespace think\swoole\concerns;

use think\helper\Arr;
use think\tracing\reporter\RedisReporter;
use think\tracing\Tracer;

/**
 * 链路追踪上报进程
 */
trait InteractsWithTracing
{
    protected function prepareTracing()
    {
        if (class_exists(Tracer::class)) {
            $tracers  = $this->container->config->get('tracing.tracers');
            $hasAsync = false;
            foreach ($tracers as $name => $tracer) {
                if (Arr::get($tracer, 'async', false)) {
                    $this->addWorker(function () use ($name) {
                        $tracer = $this->app->make(Tracer::class)->tracer($name);

                        $tracer->report();
                    }, "tracing [{$name}]");
                    $hasAsync = true;
                }
            }

            if ($hasAsync) {
                $this->onEvent('workerStart', function () {
                    $this->bindTracingRedisReporter();
                });
            }
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
