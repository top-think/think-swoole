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

use Swoole\Process;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Cache;
use think\facade\Config;
use think\swoole\Swoole as SwooleServer;

/**
 * Swoole 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole.php文件进行参数配置
 */
class Swoole extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->setDescription('Swoole HTTP Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        if (in_array($action, ['start', 'stop', 'reload', 'restart'])) {
            $this->$action($output);
        } else {
            $output->writeln("Invalid argument action:{$action}, Expected start|stop|restart|reload .");
        }
    }

    protected function start(Output $output)
    {
        $pid = $this->getPid();

        if ($this->isRunning($pid)) {
            $output->writeln('swoole http server process is already running.');
            exit(1);
        }

        $output->writeln('Starting swoole http server...');
        $option = Config::pull('swoole');

        $host = !empty($option['host']) ? $option['host'] : '0.0.0.0';
        $port = !empty($option['port']) ? $option['port'] : 9501;

        $swoole = new SwooleServer($host, $port);
        $swoole->option($option);

        $output->writeln("Swoole http server started: <http://{$host}:{$port}>");
        $output->writeln('You can exit with <info>`CTRL-C`</info>');

        $swoole->start();
    }

    protected function reload(Output $output)
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            $output->writeln('no swoole http server process running.');
            exit(1);
        }

        $output->writeln('Reloading swoole_http_server...');
        Process::kill($pid, SIGUSR1);
        $output->writeln('> success');
    }

    protected function stop(Output $output)
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            $output->writeln('no swoole http server process running.');
            exit(1);
        }

        $output->writeln('Stopping swoole http server...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $output->writeln('> success');
    }

    protected function restart(Output $output)
    {
        $pid = $this->getPid();

        if ($pid) {
            $this->stop($output);
        }

        $this->start($output);
    }

    protected function getPid()
    {
        return Cache::get('swoole_pid');
    }

    protected function removePid()
    {
        Cache::rm('swoole_pid');
    }

    protected function isRunning($pid)
    {
        Process::kill($pid, 0);

        return !swoole_errno();
    }
}
