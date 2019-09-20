<?php

namespace think\swoole\contract;

use think\Container;
use think\swoole\Sandbox;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox);
}
