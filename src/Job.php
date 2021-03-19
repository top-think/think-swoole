<?php

declare(strict_types=1);

namespace think\swoole;

class Job
{
    public $name;

    public $params;

    public function __construct($name, $params)
    {
        $this->name   = $name;
        $this->params = $params;
    }
}
