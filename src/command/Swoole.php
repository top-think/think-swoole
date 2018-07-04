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
use think\console\input\Option;
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
    protected $config = [];
    protected $pid;

    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole HTTP Server for ThinkPHP');
    }

    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        $this->config = Config::pull('swoole');

        if (in_array($action, ['start', 'stop', 'reload', 'restart'])) {
            $this->$action();
        } else {
            $output->writeln("Invalid argument action:{$action}, Expected start|stop|restart|reload .");
        }
    }

    /**
     * 启动server
     * @access protected
     * @return void
     */
    protected function start()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->output->writeln('swoole http server process is already running.');
            exit(1);
        }

        $this->output->writeln('Starting swoole http server...');

        $host = !empty($this->config['host']) ? $this->config['host'] : '0.0.0.0';
        $port = !empty($this->config['port']) ? $this->config['port'] : 9501;
        $ssl  = !empty($this->config['open_http2_protocol']);

        $swoole = new SwooleServer($host, $port, $ssl);

        // 开启守护进程模式
        if ($this->input->hasOption('daemon')) {
            $this->config['daemonize'] = true;
        }

        // 设置应用目录
        $swoole->setAppPath($this->config['app_path']);

        // 设置服务器参数
        $swoole->option($this->config);

        $this->output->writeln("Swoole http server started: <http://{$host}:{$port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $swoole->start();
    }

    /**
     * 柔性重启server
     * @access protected
     * @return void
     */
    protected function reload()
    {
        // 柔性重启使用管理PID
        $pid = $this->getManagerPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('no swoole http server process running.');
            exit(1);
        }

        $this->output->writeln('Reloading swoole_http_server...');
        Process::kill($pid, SIGUSR1);
        $this->output->writeln('> success');
    }

    /**
     * 停止server
     * @access protected
     * @return void
     */
    protected function stop()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('no swoole http server process running.');
            exit(1);
        }

        $this->output->writeln('Stopping swoole http server...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $this->output->writeln('> success');
    }

    /**
     * 重启server
     * @access protected
     * @return void
     */
    protected function restart()
    {
        $pid = $this->getMasterPid();

        if ($this->isRunning($pid)) {
            $this->stop();
        }

        $this->start();
    }

    /**
     * 获取主进程PID
     * @access protected
     * @return int
     */
    protected function getMasterPid()
    {
        $masterPid = Cache::get('swoole_master_pid');

        if ($masterPid) {
            return $masterPid;
        }

        $pidFile = $this->config['pid_file'];

        if (is_file($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
            Cache::set('swoole_master_pid', $masterPid);
        }

        return $masterPid;
    }

    /**
     * 获取管理进程PID
     * @access protected
     * @return int
     */
    protected function getManagerPid()
    {
        $managerPid = Cache::get('swoole_manager_pid');

        if ($managerPid) {
            return $managerPid;
        }

        $pidFile = dirname($this->config['pid_file']) . '/swoole_manager.pid';

        if (is_file($pidFile)) {
            $managerPid = (int) file_get_contents($pidFile);
            Cache::set('swoole_manager_pid', $managerPid);
        }

        return $managerPid;
    }

    /**
     * 删除PID文件
     * @access protected
     * @return void
     */
    protected function removePid()
    {
        $masterPid = $this->config['pid_file'];

        if (is_file($masterPid)) {
            unlink($masterPid);
        }

        $managerPid = dirname($this->config['pid_file']) . '/swoole_manager.pid';

        if (is_file($managerPid)) {
            unlink($managerPid);
        }

        Cache::rm('swoole_master_pid');
        Cache::rm('swoole_manager_pid');
    }

    /**
     * 判断PID是否在运行
     * @access protected
     * @param  int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        Process::kill($pid, 0);

        return !swoole_errno();
    }
}
