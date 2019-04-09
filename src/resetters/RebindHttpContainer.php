<?php

namespace think\swoole\resetters;

use think\Container;
use think\Http;
use think\swoole\Sandbox;

/**
 * Class RebindHttpContainer
 * @package think\swoole\resetters
 * @property Container $app;
 */
class RebindHttpContainer implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $http = $app->make(Http::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetHttp = $closure->bindTo($http, $http);
        $resetHttp();

        return $app;
    }
}
