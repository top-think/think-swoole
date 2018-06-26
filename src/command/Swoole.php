<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\swoole\facade\Swoole as SwooleServer;

/**
 * Swoole 命令行
 */
class Swoole extends Command
{
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
        $run = $input->getArgument('run');

        if ('start' == $run) {
            $host = $input->getOption('host');
            $port = $input->getOption('port');

            $option = Config::pull('swoole');

            $swoole = SwooleServer::instance($host, $port);
            $swoole->option($option);

            $output->writeln(sprintf('SwooleServer is started On <http://%s:%s/>', $host, $port));
            $output->writeln(sprintf('You can exit with <info>`CTRL-C`</info>'));

            $swoole->start();
        } else {
            SwooleServer::$run();
        }
    }

}
