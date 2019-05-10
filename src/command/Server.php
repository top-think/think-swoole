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
use think\console\input\Argument;
use think\swoole\Swoole;
use Throwable;

/**
 * Swoole HTTP 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole.php文件进行参数配置
 */
class Server extends Command
{
    /**
     * The configs for this package.
     *
     * @var array
     */
    protected $config;

    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->setDescription('Swoole HTTP Server for ThinkPHP');
    }

    public function handle()
    {
        $action = $this->input->getArgument('action');

        $this->init();

        if (in_array($action, ['start', 'stop', 'reload', 'restart'])) {
            $this->$action();
        } else {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload .</error>");
        }
    }

    protected function init()
    {
        $this->config = $this->app->config->get('swoole');
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
            $this->output->writeln('<error>swoole http server process is already running.</error>');
            return;
        }

        $this->output->writeln('Starting swoole http server...');

        /** @var Swoole $swoole */
        $swoole = $this->app->make(Swoole::class);

        if ($this->config['auto_reload']) {
            //热更新
            /** @var \Swoole\Server $server */
            $server = $this->app->make(\think\swoole\facade\Server::class);

            $server->addProcess($this->getHotReloadProcess($server, (int) $this->config['auto_reload']));
        }

        $host = $this->config['server']['host'];
        $port = $this->config['server']['port'];

        $this->output->writeln("Swoole http server started: <http://{$host}:{$port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $swoole->run();
    }

    /**
     * @param \Swoole\Server $server
     * @param int            $ms
     * @return Process
     */
    protected function getHotReloadProcess($server, $ms)
    {
        return new Process(function () use ($ms, $server) {
            //todo 这里可以使用fswatch来检查文件变化

            swoole_timer_tick($ms > 0 ? $ms : 3000, function () use ($server) {
                $server->reload();
            });
        }, false, 0);
    }

    /**
     * 柔性重启server
     * @access protected
     * @return void
     */
    protected function reload()
    {
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole http server process running.</error>');
            return;
        }

        $this->output->writeln('Reloading swoole http server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (!$isRunning) {
            $this->output->error('> failure');

            return;
        }

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
            $this->output->writeln('<error>no swoole http server process running.</error>');
            return;
        }

        $this->output->writeln('Stopping swoole http server...');

        $isRunning = $this->killProcess($pid, SIGTERM, 15);

        if ($isRunning) {
            $this->output->error('Unable to stop the swoole_http_server process.');
            return;
        }

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
        $pidFile = $this->getPidPath();

        if (file_exists($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
        } else {
            $masterPid = 0;
        }

        return $masterPid;
    }

    /**
     * Get Pid file path.
     *
     * @return string
     */
    protected function getPidPath()
    {
        return $this->config['server']['options']['pid_file'];
    }

    /**
     * 删除PID文件
     * @access protected
     * @return void
     */
    protected function removePid()
    {
        $masterPid = $this->getPidPath();

        if (file_exists($masterPid)) {
            unlink($masterPid);
        }
    }

    /**
     * 杀死进程
     * @param     $pid
     * @param     $sig
     * @param int $wait
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * 判断PID是否在运行
     * @access protected
     * @param int $pid
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        try {
            return Process::kill($pid, 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}
