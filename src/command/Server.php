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
use Swoole\WebSocket\Server as WebsocketServer;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\swoole\Manager;
use think\swoole\PidManager;

/**
 * Swoole HTTP 命令行，支持操作：start|stop|restart|reload
 * 支持应用配置目录下的swoole.php文件进行参数配置
 */
class Server extends Command
{
    public function configure()
    {
        $this->setName('swoole')
            ->addArgument('action', Argument::OPTIONAL, 'start|stop|restart|reload', 'start')
            ->setDescription('Swoole HTTP Server for ThinkPHP');
    }

    protected function initialize(Input $input, Output $output)
    {
        $this->app->bind(\Swoole\Server::class, function () {
            return $this->createSwooleServer();
        });

        $this->app->bind(PidManager::class, function () {
            return new PidManager($this->app->config->get('swoole.server.options.pid_file'));
        });
    }

    public function handle()
    {
        $this->checkEnvironment();

        $action = $this->input->getArgument('action');

        if (in_array($action, ['start', 'stop', 'reload', 'restart'])) {
            $this->app->invokeMethod([$this, $action], [], true);
        } else {
            $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload .</error>");
        }
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

        if (!version_compare(swoole_version(), '4.4.8', 'ge')) {
            $this->output->error('Your Swoole version must be higher than `4.4.8`.');

            exit(1);
        }
    }

    /**
     * 启动server
     * @access protected
     * @param Manager $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function start(Manager $manager, PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->output->writeln('<error>swoole http server process is already running.</error>');
            return;
        }

        $this->output->writeln('Starting swoole http server...');

        $host = $manager->getConfig('server.host');
        $port = $manager->getConfig('server.port');

        $this->output->writeln("Swoole http server started: <http://{$host}:{$port}>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        $manager->run();
    }

    /**
     * 柔性重启server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function reload(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no swoole http server process running.</error>');
            return;
        }

        $this->output->writeln('Reloading swoole http server...');

        if (!$manager->killProcess(SIGUSR1)) {
            $this->output->error('> failure');

            return;
        }

        $this->output->writeln('> success');
    }

    /**
     * 停止server
     * @access protected
     * @param PidManager $manager
     * @return void
     */
    protected function stop(PidManager $manager)
    {
        if (!$manager->isRunning()) {
            $this->output->writeln('<error>no swoole http server process running.</error>');
            return;
        }

        $this->output->writeln('Stopping swoole http server...');

        $isRunning = $manager->killProcess(SIGTERM, 15);

        if ($isRunning) {
            $this->output->error('Unable to stop the swoole_http_server process.');
            return;
        }

        $this->output->writeln('> success');
    }

    /**
     * 重启server
     * @access protected
     * @param Manager $manager
     * @param PidManager $pidManager
     * @return void
     */
    protected function restart(Manager $manager, PidManager $pidManager)
    {
        if ($pidManager->isRunning()) {
            $this->stop($pidManager);
        }

        $this->start($manager, $pidManager);
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $isWebsocket = $this->app->config->get('swoole.websocket.enable', false);

        $serverClass = $isWebsocket ? WebsocketServer::class : HttpServer::class;
        $config      = $this->app->config;
        $host        = $config->get('swoole.server.host');
        $port        = $config->get('swoole.server.port');
        $socketType  = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode        = $config->get('swoole.server.mode', SWOOLE_PROCESS);

        /** @var \Swoole\Server $server */
        $server = new $serverClass($host, $port, $mode, $socketType);

        $options = $config->get('swoole.server.options');

        $server->set($options);
        return $server;
    }

}
