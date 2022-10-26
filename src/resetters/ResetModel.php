<?php

namespace think\swoole\resetters;

use think\App;
use think\Model;
use think\swoole\contract\ResetterInterface;
use think\swoole\Sandbox;

class ResetModel implements ResetterInterface
{

    public function handle(App $app, Sandbox $sandbox)
    {
        if (class_exists(Model::class)) {
            Model::setInvoker(function (...$args) use ($sandbox) {
                return $sandbox->getApplication()->invoke(...$args);
            });
        }
    }
}
