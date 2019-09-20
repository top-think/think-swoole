<?php

namespace think\swoole\middleware;

use Closure;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use think\Request;

class ResetVarDumper
{
    protected $cloner;

    public function __construct()
    {
        $this->cloner = new VarCloner();
        $this->cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
    }

    public function handle(Request $request, Closure $next)
    {
        $prevHandler = VarDumper::setHandler(function ($var) {
            $dumper = new HtmlDumper();
            $dumper->dump($this->cloner->cloneVar($var));
        });
        $response    = $next($request);
        VarDumper::setHandler($prevHandler);
        return $response;
    }
}
