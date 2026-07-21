<?php

namespace think\swoole\ipc\driver;

use Closure;
use Redis as PHPRedis;
use Smf\ConnectionPool\ConnectionPool;
use Smf\ConnectionPool\Connectors\PhpRedisConnector;
use Swoole\Coroutine;
use think\helper\Arr;
use think\swoole\ipc\Driver;
use think\swoole\Pool;
use Throwable;

class Redis extends Driver
{

    /** @var ConnectionPool */
    protected $pool;

    /** @var int 重连间隔（秒） */
    protected $retryInterval = 1;

    public function getType()
    {
        return SWOOLE_IPC_NONE;
    }

    public function prepare(\Swoole\Process\Pool $pool)
    {
    }

    public function subscribe()
    {
        $config = $this->config;

        $this->pool = new ConnectionPool(
            Pool::pullPoolConfig($config),
            new PhpRedisConnector(),
            $config
        );

        $this->manager->getPools()->add('ipc.redis', $this->pool);

        Coroutine::create(function () {
            // 订阅可能因连接断开、Redis 重启、网络抖动等异常中断，
            // 这里用循环 + 异常捕获保证协程不会因单次异常退出而永久停止接收消息。
            while (true) {
                try {
                    $this->runWithRedis(function (PHPRedis $redis) {
                        $redis->setOption(PHPRedis::OPT_READ_TIMEOUT, -1);
                        $redis->subscribe([$this->getChannel($this->workerId)], function ($redis, $channel, $message) {
                            // 单条消息解析失败不应中断整个订阅
                            try {
                                $this->manager->triggerEvent('message', unserialize($message));
                            } catch (Throwable $e) {
                                $this->manager->logServerError($e);
                            }
                        });
                    });
                } catch (Throwable $e) {
                    $this->manager->logServerError($e);
                    // 退避后重连，避免异常时的忙循环
                    Coroutine::sleep($this->retryInterval);
                }
            }
        });
    }

    public function publish($workerId, $message, ?string $nodeId = null)
    {
        $this->runWithRedis(function (PHPRedis $redis) use ($message, $workerId, $nodeId) {
            $redis->publish($this->getChannel($workerId, $nodeId), serialize($message));
        });
    }

    /**
     * 获取 Redis 频道名：{prefix}{nodeId}:{workerId}
     */
    protected function getChannel(int $workerId, ?string $nodeId = null): string
    {
        return $this->getPrefix() . ($nodeId ?? $this->manager->getNodeId()) . ':' . $workerId;
    }

    protected function getPrefix()
    {
        return Arr::get($this->config, 'prefix', 'swoole:ipc:');
    }

    protected function runWithRedis(Closure $callable)
    {
        $redis = $this->pool->borrow();
        try {
            return $callable($redis);
        } finally {
            $this->pool->return($redis);
        }
    }
}
