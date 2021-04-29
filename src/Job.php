<?php

namespace think\swoole;

class Job
{
    public $name;

    public $params;

    public function __construct($name, $params = [])
    {
        $this->name   = $name;
        $this->params = $params;
    }

    public function run(\think\App $app)
    {
        $job = $this->name;
        if (!is_array($job)) {
            $job = [$job, 'handle'];
        }

        [$class, $method] = $job;
        $object = $app->invokeClass($class, $this->params);
        return $object->{$method}();
    }
}
