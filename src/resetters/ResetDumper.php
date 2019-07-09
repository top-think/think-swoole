<?php

namespace think\swoole\resetters;

use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use think\Container;
use think\swoole\Sandbox;

class ResetDumper implements ResetterContract
{

    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox)
    {
        //支持symfony/var-dumper
        if (class_exists("Symfony\Component\VarDumper\VarDumper")) {
            $cloner = new VarCloner();
            $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
            $dumper = new HtmlDumper();
            VarDumper::setHandler(function ($var) use ($cloner, $dumper) {
                $dumper->dump($cloner->cloneVar($var));
            });
        }
    }
}
