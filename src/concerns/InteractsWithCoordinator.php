<?php

namespace think\swoole\concerns;

use think\swoole\Coordinator;

trait InteractsWithCoordinator
{
    /** @var Coordinator[] */
    protected $coordinators = [];

    public function resumeCoordinator($name, $callback)
    {
        $this->coordinators[$name] = new Coordinator();
        $callback();
        $this->coordinators[$name]->resume();
    }

    public function waitCoordinator($name, $timeout = -1)
    {
        if (isset($this->coordinators[$name])) {
            $this->coordinators[$name]->yield($timeout);
        }
    }
}
