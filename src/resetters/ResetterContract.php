<?php

namespace think\swoole\resetters;

use think\Container;
use think\swoole\Sandbox;

interface ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox);
}