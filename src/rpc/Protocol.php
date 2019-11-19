<?php

namespace think\swoole\rpc;

class Protocol
{
    const ACTION_INTERFACE = '@action_interface';
    const FILE             = '@file';

    /**
     * @var string
     */
    private $interface = '';

    /**
     * @var string
     */
    private $method = '';

    /**
     * @var array
     */
    private $params = [];

    /**
     * Replace constructor
     *
     * @param string $interface
     * @param string $method
     * @param array  $params
     *
     * @return Protocol
     */
    public static function make(string $interface, string $method, array $params)
    {
        $instance = new static();

        $instance->interface = $interface;
        $instance->method    = $method;
        $instance->params    = $params;

        return $instance;
    }

    /**
     * @return string
     */
    public function getInterface(): string
    {
        return $this->interface;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

}
