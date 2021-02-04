<?php

namespace think\swoole\queue;

use Swoole\Process;
use Swoole\Timer;
use think\helper\Arr;
use think\queue\event\JobFailed;
use think\queue\Worker;
use think\swoole\concerns\WithContainer;

class Manager
{
    use WithContainer;

    /**
     * @var Process\Manager
     */
    protected $pm;

    public function run(): void
    {
        $this->pm = new Process\Manager();

        $worker = $this->getConfig('queue.worker', ['default' => []]);

        $this->listenForEvents();
        $this->createWorkers($worker);

        $this->pm->start();
    }

    /**
     * 注册事件
     */
    protected function listenForEvents()
    {
        $this->container->event->listen(JobFailed::class, function (JobFailed $event) {
            $this->logFailedJob($event);
        });
    }

    /**
     * 记录失败任务
     * @param JobFailed $event
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->container['queue.failer']->log(
            $event->connection,
            $event->job->getQueue(),
            $event->job->getRawBody(),
            $event->exception
        );
    }

    protected function createWorkers($consumers)
    {
        foreach ($consumers as $queue => $options) {

            if (strpos($queue, '@') !== false) {
                [$queue, $connection] = explode('@', $queue);
            } else {
                $connection = null;
            }

            $this->pm->add(function (Process\Pool $pool, $workerId) use ($options, $connection, $queue) {

                /** @var Worker $worker */
                $worker  = $this->container->make(Worker::class);
                $delay   = Arr::get($options, "delay", 0);
                $sleep   = Arr::get($options, "sleep", 3);
                $tries   = Arr::get($options, "tries", 0);
                $timeout = Arr::get($options, "timeout", 60);

                $timer = Timer::after($timeout * 1000, function () use ($pool, $workerId) {
                    $process = $pool->getProcess($workerId);
                    if ($process instanceof Process) {
                        $process->exit();
                    }
                });

                $worker->runNextJob($connection, $queue, $delay, $sleep, $tries);

                Timer::clear($timer);
            }, true);
        }
    }
}
