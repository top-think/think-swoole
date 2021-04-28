<?php

namespace think\swoole\queue;

use Swoole\Process;
use Swoole\Timer;

class Worker extends \think\queue\Worker
{
    public function waitRunJob(Process $process, $connection, $queue, $delay = 0, $sleep = 3, $maxTries = 0, $timeout = 60)
    {
        while (true) {
            $job = $this->getNextJob(
                $this->queue->connection($connection),
                $queue
            );

            if ($job) {
                $timer = Timer::after($timeout * 1000, function () use ($process) {
                    $process->exit();
                });
                $this->runJob($job, $connection, $maxTries, $delay);

                Timer::clear($timer);

                break;
            } else {
                $this->sleep($sleep);
            }
        }
    }
}
