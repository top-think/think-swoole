<?php

namespace think\swoole;

use think\swoole\coroutine\Context;
use think\swoole\rpc\client\Proxy;

class App extends \think\App
{
    public function runningInConsole()
    {
        return !!Context::getData('_fd');
    }

    protected function isRpcInterface($abstract)
    {
        if (interface_exists($abstract) && defined($abstract . '::RPC')) {
            return true;
        }
        return false;
    }

    public function make(string $abstract, array $vars = [], bool $newInstance = false)
    {
        if ($this->isRpcInterface($abstract) && !$this->bound($abstract)) {
            //rpc接口
            $this->bind($abstract, Proxy::getClassName($abstract));
        }

        return parent::make($abstract, $vars, $newInstance);
    }
}
