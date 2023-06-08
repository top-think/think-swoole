<?php

namespace think\swoole\concerns;

use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use think\helper\Arr;
use think\swoole\Pool;
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
                    $this->bindTracingRedisPool();
                    $this->bindTracingRedisReporter();
                });
            }
        }
    }

    protected function bindTracingRedisReporter()
    {
        $this->getApplication()->bind(RedisReporter::class, function ($name) {

            $pool = $this->getPools()->get("tracing.redis");

            $redis = new class($pool) {
                protected $pool;

                public function __construct($pool)
                {
                    $this->pool = $pool;
                }

                public function __call($name, $arguments)
                {
                    $client = $this->pool->borrow();
                    try {
                        return call_user_func_array([$client, $name], $arguments);
                    } finally {
                        $this->pool->return($client);
                    }
                }
            };

            return new RedisReporter($name, $redis);
        });
    }

    protected function bindTracingRedisPool()
    {
        $config = $this->container->config->get('tracing.redis');

        $pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            new PhpRedisConnector(),
            $config
        );
        $this->getPools()->add("tracing.redis", $pool);
    }
}
