<?php

namespace think\swoole\concerns;

use think\swoole\Coordinator;

trait InteractsWithCoordinator
{
    protected $coordinators = [];

    /**
     * @param string $name
     * @return Coordinator
     */
    public function getCoordinator(string $name)
    {
        if (!isset($this->coordinators[$name])) {
            $this->coordinators[$name] = new Coordinator();
        }

        return $this->coordinators[$name];
    }
}
