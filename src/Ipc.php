<?php

namespace think\swoole;

/**
 *
 * @mixin \think\swoole\ipc\driver\UnixSocket
 */
class Ipc extends \think\Manager
{

    protected $namespace = "\\think\\swoole\\ipc\\driver\\";

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("swoole.ipc.{$name}", []);
    }

    public function getDefaultDriver()
    {
        return $this->app->config->get('swoole.ipc.type', 'unix_socket');
    }
}
