<?php

namespace think\swoole\concerns;

use think\swoole\Coordinator;

trait InteractsWithCoordinator
{
    /** @var Coordinator[] */
    protected $coordinators = [];

    public function resumeCoordinator($name)
    {
        if (!isset($this->coordinators[$name])) {
            $this->coordinators[$name] = new Coordinator();
        }
        $this->coordinators[$name]->resume();
    }

    public function waitCoordinator($name, $timeout = -1)
    {
        if (isset($this->coordinators[$name])) {
            $this->coordinators[$name]->yield($timeout);
        }
    }
}
