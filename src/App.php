<?php

namespace think\swoole;

use think\swoole\coroutine\Context;

class App extends \think\App
{
    public function runningInConsole(): bool
    {
        return Context::hasData('_fd');
    }

    public function clearInstances()
    {
        $this->instances = [];
    }
}
