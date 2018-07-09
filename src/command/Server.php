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

use Swoole\Http\Server as HttpServer;
use Swoole\Process;
use Swoole\Server as SwooleServer;
use Swoole\Websocket\Server as Websocket;
use think\console\input\Argument;
use think\console\input\Option;
use think\facade\Config;
use think\facade\Env;
use think\swoole\Server as ThinkServer;

/**
 * Swoole 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole.php文件进行参数配置
 */
class Server extends Swoole
{
    public function configure()
    {
        $this->setName('swoole:server')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole Server for ThinkPHP');
    }

    protected function init()
    {
        $this->config = Config::pull('swoole_server');

        if (!empty($this->config['pid_file'])) {
            $this->config['pid_file'] = Env::get('runtime_path') . 'swoole_server.pid';
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
            $this->output->writeln('<error>swoole server process is already running.</error>');
            return false;
        }

        $this->output->writeln('Starting swoole server...');

        if (!empty($this->config['swoole_class'])) {
            $class = $this->config['swoole_class'];

            if (class_exists($class)) {
                $swoole = new $class;
                if (!$swoole instanceof ThinkServer) {
                    $this->output->writeln("<error>Swoole Server Class Must extends \\think\\swoole\\Server</error>");
                    return false;
                }
            } else {
                $this->output->writeln("<error>Swoole Server Class Not Exists : {$class}</error>");
                return false;
            }
        } else {
            $host = !empty($this->config['host']) ? $this->config['host'] : '0.0.0.0';
            $port = !empty($this->config['port']) ? $this->config['port'] : 9508;
            $type = !empty($this->config['type']) ? $this->config['type'] : 'socket';

            switch ($type) {
                case 'socket':
                    $swoole = new Websocket($host, $port);
                    break;
                case 'http':
                    $swoole = new HttpServer($host, $port);
                    break;
                default:
                    $swoole = new SwooleServer($host, $port, $this->config['mode'], $this->config['sockType']);
            }

            // 开启守护进程模式
            if ($this->input->hasOption('daemon')) {
                $this->config['daemonize'] = true;
            }

            foreach ($this->config as $name => $val) {
                if (0 === strpos($name, 'on')) {
                    $swoole->on(substr($name, 2), $val);
                    unset($this->config[$name]);
                }
            }

            // 设置服务器参数
            $swoole->set($this->config);

            $this->output->writeln("Swoole http server started: <http://{$host}:{$port}>");
            $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

            // 启动服务
            $swoole->start();
        }
    }

    /**
     * 柔性重启server
     * @access protected
     * @return void
     */
    protected function reload()
    {
        // 柔性重启使用管理PID
        $pid = $this->getMasterPid();

        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Reloading swoole server...');
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
            $this->output->writeln('<error>no swoole server process running.</error>');
            return false;
        }

        $this->output->writeln('Stopping swoole server...');

        Process::kill($pid, SIGTERM);
        $this->removePid();

        $this->output->writeln('> success');
    }

}
