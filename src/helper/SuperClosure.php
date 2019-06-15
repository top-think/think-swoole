<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/8
 * Email:499873958@qq.com
 */

namespace think\swoole\helper;

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
        // TODO: Implement __invoke() method.
        $args = func_get_args();
        return \think\facade\App::invokeFunction($this->closure, $args);
    }
    
    final public function call(...$args)
    {
        return \think\facade\App::invokeFunction($this->closure, $args);
    }
}
