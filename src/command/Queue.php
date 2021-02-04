<?php

namespace think\swoole\command;

use think\console\Command;
use think\swoole\queue\Manager;

class Queue extends Command
{
    public function configure()
    {
        $this->setName('swoole:queue')
            ->setDescription('Listen to a given queue');
    }

    public function handle(Manager $manager)
    {
        $this->output->writeln('Swoole queue worker started');
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        $manager->run();
    }
}
