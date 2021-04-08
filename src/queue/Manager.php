<?php

namespace think\swoole\queue;

use Closure;
use Swoole\Constant;
use Swoole\Process;
use Swoole\Process\Pool;
use Swoole\Server;
use Swoole\Timer;
use think\helper\Arr;
use think\queue\event\JobFailed;
use think\queue\Worker;
use think\swoole\concerns\InteractsWithRpcClient;
use think\swoole\concerns\WithContainer;
use function Swoole\Coroutine\run;

class Manager
{
    use WithContainer, InteractsWithRpcClient;

    /**
     * @var Closure[]
     */
    protected $workers = [];

    public function attachToServer(Server $server)
    {
        $this->listenForEvents();
        $this->createWorkers();
        foreach ($this->workers as $worker) {
            $server->addProcess(new Process($worker, false, 0, true));
        }
    }

    public function run(): void
    {
        @cli_set_process_title('swoole queue: manager process');

        $this->listenForEvents();
        $this->createWorkers();

        $pool = new Pool(count($this->workers));

        $pool->on(Constant::EVENT_WORKER_START, function (Pool $pool, int $workerId) {
            $process = $pool->getProcess($workerId);
            run($this->workers[$workerId], $process);
        });

        $pool->start();
    }

    protected function getApplication()
    {
        return $this->container;
    }

    protected function createWorkers()
    {
        $workers = $this->getConfig('queue.workers', []);

        foreach ($workers as $queue => $options) {

            if (strpos($queue, '@') !== false) {
                [$queue, $connection] = explode('@', $queue);
            } else {
                $connection = null;
            }

            $this->workers[] = function (Process $process) use ($options, $connection, $queue) {

                @cli_set_process_title('swoole queue: worker process');

                $this->bindRpcInterface();

                /** @var Worker $worker */
                $worker = $this->container->make(Worker::class);

                $delay   = Arr::get($options, 'delay', 0);
                $sleep   = Arr::get($options, 'sleep', 3);
                $tries   = Arr::get($options, 'tries', 0);
                $timeout = Arr::get($options, 'timeout', 60);

                $timer = Timer::after($timeout * 1000, function () use ($process) {
                    $process->exit();
                });

                $worker->runNextJob($connection, $queue, $delay, $sleep, $tries);

                Timer::clear($timer);
            };
        }
    }

    protected function createRpcConnector($name)
    {
        return $this->getConfig("rpc.client.{$name}");
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

}
