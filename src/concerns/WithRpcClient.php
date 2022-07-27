<?php

namespace think\swoole\concerns;

use think\App;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\helper\Arr;
use think\swoole\rpc\client\Gateway;
use think\swoole\rpc\client\Proxy;
use think\swoole\rpc\JsonParser;
use Throwable;
use function Swoole\Coroutine\run;

/**
 * 在命令行里使用RPC
 * @property App $app
 */
trait WithRpcClient
{
    public function __construct()
    {
        if (!($this instanceof Command)) {
            throw new \RuntimeException('Trait `WithRpcClient` only can be used in Command');
        }
        parent::__construct();
    }

    protected function execute(Input $input, Output $output)
    {
        $this->bindRpcInterface();
        run(function () {
            $this->app->invoke([$this, 'handle']);
        });
    }

    protected function bindRpcInterface()
    {
        //引入rpc接口文件
        if (file_exists($rpc = $this->app->getBasePath() . 'rpc.php')) {
            $rpcServices = (array) include $rpc;

            //绑定rpc接口
            try {
                foreach ($rpcServices as $name => $abstracts) {

                    $config = $this->app->config->get("swoole.rpc.client.{$name}", []);

                    $parserClass = Arr::pull($config, 'parser', JsonParser::class);
                    $tries       = Arr::pull($config, 'tries', 2);
                    $middleware  = Arr::pull($config, 'middleware', []);

                    $parser  = $this->app->make($parserClass);
                    $gateway = new Gateway($config, $parser, $tries);

                    foreach ($abstracts as $abstract) {
                        $this->app->bind($abstract, function (App $app) use ($middleware, $gateway, $name, $abstract) {
                            return $app->invokeClass(Proxy::getClassName($name, $abstract), [$gateway, $middleware]);
                        });
                    }
                }
            } catch (Throwable $e) {
            }
        }
    }
}
