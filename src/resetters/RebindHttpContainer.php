<?php

namespace think\swoole\resetters;

use think\Container;
use think\Http;
use think\swoole\Sandbox;

class RebindHttpContainer implements ResetterContract
{
    /**
     * @var Container
     */
    protected $app;

    public function handle(Container $app, Sandbox $sandbox)
    {
        $kernel = $app->make(Http::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetKernel = $closure->bindTo($kernel, $kernel);
        $resetKernel();

        return $app;
    }
}
