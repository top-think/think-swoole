<?php

namespace think\swoole\concerns;

use think\App;

trait WithContainer
{

    /**
     * @var App
     */
    protected $container;

    /**
     * Manager constructor.
     * @param App $container
     */
    public function __construct(App $container)
    {
        $this->container = $container;
    }

    /**
     * 获取配置
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $name, $default = null)
    {
        return $this->container->config->get("swoole.{$name}", $default);
    }

    /**
     * 触发事件
     * @param string $event
     * @param null $params
     */
    protected function triggerEvent(string $event, $params = null): void
    {
        $this->container->event->trigger("swoole.{$event}", $params);
    }

    /**
     * 监听事件
     * @param string $event
     * @param        $listener
     * @param bool $first
     */
    public function onEvent(string $event, $listener, bool $first = false): void
    {
        $this->container->event->listen("swoole.{$event}", $listener, $first);
    }
}
