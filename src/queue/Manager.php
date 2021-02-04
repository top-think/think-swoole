<?php

namespace think\swoole\queue;

use Swoole\Process;
use think\helper\Arr;
use think\swoole\concerns\WithApplication;
use think\swoole\concerns\WithContainer;

class Manager
{
    use WithContainer,
        WithApplication;

    protected $consumers = [];

    public function run(): void
    {
        $consumers = [
            'default'       => [
                'tries' => 3,
            ],
            'default@mysql' => [
                'tries' => 3,
            ],
        ];

        $this->prepareApplication();

        $this->createMonitor();

        $this->createConsumers($consumers);

        //子进程回收
        Process::signal(SIGCHLD, function ($sig) {
            //必须为false，非阻塞模式
            while ($ret = Process::wait(false)) {
                echo "PID={$ret['pid']}\n";
            }
        });
    }

    protected function createMonitor()
    {

    }

    protected function createConsumers($consumers)
    {
        foreach ($consumers as $queue => $options) {
            /** @var Worker $worker */
            $worker = $this->app->make(Worker::class);

            if (strpos($queue, '@') !== false) {
                [$queue, $connection] = explode('@', $queue);
            } else {
                $connection = null;
            }

            $process = new Process(function () use ($options, $queue, $connection, $worker) {
                $delay   = Arr::get($options, "delay", 0);
                $sleep   = Arr::get($options, "sleep", 3);
                $tries   = Arr::get($options, "tries", 0);
                $memory  = Arr::get($options, "memory", 128);
                $timeout = Arr::get($options, "timeout", 60);

                $worker->daemon($connection, $queue, $delay, $sleep, $tries, $memory, $timeout);
            }, false, 0, true);

            $pid = $process->start();

            $this->consumers[$pid] = $worker;
        }
    }
}
