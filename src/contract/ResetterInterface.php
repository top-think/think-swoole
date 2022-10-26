<?php

namespace think\swoole\contract;

use think\App;
use think\swoole\Sandbox;

interface ResetterInterface
{
    /**
     * "handle" function for resetting app.
     *
     * @param \think\App $app
     * @param Sandbox $sandbox
     */
    public function handle(App $app, Sandbox $sandbox);
}
