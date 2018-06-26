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
use think\console\Output;
use think\facade\Config;
use think\swoole\Swoole as SwooleServer;

/**
 * Swoole 命令行
 */
class Swoole extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('run', Argument::OPTIONAL, "start|stop", 'start')
            ->setDescription('Swoole HTTP Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        $run = $input->getArgument('run');

        $option = Config::pull('swoole');

        $host = !empty($option['host']) ? $option['host'] : '0.0.0.0';
        $port = !empty($option['port']) ? $option['port'] : 9501;

        $swoole = new SwooleServer($host, $port);
        $swoole->option($option);

        $output->writeln(sprintf('SwooleServer is started On <http://%s:%s/>', $host, $port));
        $output->writeln(sprintf('You can exit with <info>`CTRL-C`</info>'));

        $swoole->start();
    }

}
