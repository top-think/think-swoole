<?php

namespace think\swoole\concerns;

use think\swoole\Redis;

trait InteractsWithRedis
{
    /**
     * @var Redis
     */
    protected $redis;

    protected function prepareRedis(): void
    {
        $config = $this->getConfig('redis', []);

        $this->redis = new Redis(is_array($config) ? $config : [], function (string $message) {
            $this->container->log->warning($message);
        });
    }

    protected function bindRedis(): void
    {
        $this->app->instance(Redis::class, $this->redis);
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }
}
