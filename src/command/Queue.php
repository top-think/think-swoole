<?php

namespace think\swoole\command;

use think\console\Command;
use think\console\input\Argument;
use think\swoole\queue\Manager;

class Queue extends Command
{
    public function configure()
    {
        $this->setName('swoole:queue')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->setDescription('Listen to a given queue');
    }

    public function handle(Manager $manager)
    {
        $manager->run();
    }
}
