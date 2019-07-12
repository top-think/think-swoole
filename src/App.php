<?php

namespace think\swoole;

class App extends \think\App
{
    public function runningInConsole()
    {
        return false;
    }
}
