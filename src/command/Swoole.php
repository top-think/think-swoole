<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\swoole\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\swoole\Swoole as SwooleServer;

class Swoole extends Command
{
    protected $swoole;

    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('run', Argument::OPTIONAL, "start|stop", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL,
                'The host to server the application on', '0.0.0.0')
            ->addOption('port', 'r', Option::VALUE_OPTIONAL,
                'The port to server the application on', 9501)
            ->setDescription('Built-in Swoole HTTP Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        if (!$this->swoole) {
            $host    = $input->getOption('host');
            $port    = $input->getOption('port');
            $appPath = $input->getOption('path');

            $option = Config::pull('swoole');

            $this->swoole = new SwooleServer($host, $port);
            $this->swoole->option($option);

            $output->writeln(sprintf('SwooleServer is started On <http://%s:%s/>', $host, $port));
            $output->writeln(sprintf('You can exit with <info>`CTRL-C`</info>'));
        }

        $run = $input->getArgument('run');

        $this->swoole->$run();
    }

}
