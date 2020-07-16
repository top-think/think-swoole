<?php

namespace think\swoole;

use think\swoole\coroutine\Context;

class App extends \think\App
{
    public function runningInConsole()
    {
        return Context::hasData('_fd');
    }
}
