<?php
/**
 * author:xavier
 * email:49987958@qq.com
 */

namespace think\swoole;

use SuperClosure\Serializer;

class SuperClosure
{
    private $closure;
    private $serialized;

    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    final public function __sleep()
    {
        $serializer       = new Serializer();
        $this->serialized = $serializer->serialize($this->closure);
        unset($this->closure);
        return ['serialized'];
    }

    final public function __wakeup()
    {
        $serializer    = new Serializer();
        $this->closure = $serializer->unserialize($this->serialized);
    }

    final public function __invoke()
    {
        $args = func_get_args();
        return Invoker::callUserFuncArray($this->closure, $args);
    }

    final public function call(...$args)
    {
        return Invoker::callUserFuncArray($this->closure, $args);
    }
}