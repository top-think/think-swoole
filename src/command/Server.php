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
use think\console\input\Option;
use think\swoole\Manager;

class Server extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addOption(
                'env',
                'E',
                Option::VALUE_REQUIRED,
                'Environment name',
                ''
            )
            ->setDescription('Swoole Server for ThinkPHP');
    }

    public function handle(Manager $manager)
    {
        $this->checkEnvironment();

        $this->output->writeln('Starting swoole server...');

        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $envName = $this->input->getOption('env');
        $manager->start($envName);
    }

    /**
     * 检查环境
     */
    protected function checkEnvironment()
    {
        if (!extension_loaded('swoole')) {
            $this->output->error('Can\'t detect Swoole extension installed.');

            exit(1);
        }

        if (!version_compare(swoole_version(), '4.6.0', 'ge')) {
            $this->output->error('Your Swoole version must be higher than `4.6.0`.');

            exit(1);
        }
    }
}
