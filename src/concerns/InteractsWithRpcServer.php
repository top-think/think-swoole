<?php

namespace think\swoole\concerns;

use Swoole\Coroutine\Server;
use Swoole\Coroutine\Server\Connection;
use Swoole\Process;
use think\App;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\Pool;
use think\swoole\rpc\Error;
use think\swoole\rpc\File;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\Packer;
use think\swoole\rpc\server\Dispatcher;
use Throwable;

/**
 * Trait InteractsWithRpc
 * @package think\swoole\concerns
 * @property App $app
 * @property App $container
 * @method Pool getPools()
 */
trait InteractsWithRpcServer
{
    protected function createRpcServer(Process\Pool $pool)
    {
        $this->setProcessName('rpc server process');

        $this->bindRpcParser();
        $this->bindRpcDispatcher();

        $host = $this->getConfig('rpc.server.host', '0.0.0.0');
        $port = $this->getConfig('rpc.server.port', 9000);

        $server = new Server($host, $port, false, true);

        Process::signal(SIGTERM, function () use ($pool, $server) {
            $server->shutdown();
            $pool->getProcess()->exit();
        });

        $server->handle(function (Connection $conn) {

            $this->runInSandbox(function (App $app, Dispatcher $dispatcher) use ($conn) {
                $files = [];
                while (true) {
                    //接收数据
                    $data = $conn->recv();

                    if ($data === '' || $data === false) {
                        break;
                    }
                    write:
                    if (!isset($handler)) {
                        try {
                            [$handler, $data] = Packer::unpack($data);
                        } catch (Throwable $e) {
                            //错误的包头
                            $result = Error::make(Dispatcher::INVALID_REQUEST, $e->getMessage());
                            $dispatcher->dispatch($app, $conn, $result);
                            break;
                        }
                    }

                    $result = $handler->write($data);

                    if (!empty($result)) {
                        $handler = null;
                        if ($result instanceof File) {
                            $files[] = $result;
                        } else {
                            $dispatcher->dispatch($app, $conn, $result, $files);
                            $files = [];
                        }
                    }

                    if (!empty($data)) {
                        goto write;
                    }
                }

                $conn->close();
            });
        });

        $server->start();
    }

    protected function prepareRpcServer()
    {
        if ($this->getConfig('rpc.server.enable', false)) {
            $this->addBatchWorker(swoole_cpu_num(), [$this, 'createRpcServer']);
        }
    }

    protected function bindRpcDispatcher()
    {
        $services   = $this->getConfig('rpc.server.services', []);
        $middleware = $this->getConfig('rpc.server.middleware', []);

        $this->app->make(Dispatcher::class, [$services, $middleware]);
    }

    protected function bindRpcParser()
    {
        $parserClass = $this->getConfig('rpc.server.parser', JsonParser::class);

        $this->app->bind(ParserInterface::class, $parserClass);
        $this->app->make(ParserInterface::class);
    }

}
