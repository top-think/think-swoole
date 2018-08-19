<?php
/**
 * author:xavier
 * email:49987958@qq.com
 */
namespace think\swoole;


class Invoker
{
    public static function callUserFunc(callable $callable,...$params)
    {
        if(SWOOLE_VERSION >1){
            if($callable instanceof \Closure){
                return $callable(...$params);
            }else if(is_array($callable) && is_object($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class->$method(...$params);
            }else if(is_array($callable) && is_string($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class::$method(...$params);
            }else if(is_string($callable)){
                return $callable(...$params);
            }else{
                return null;
            }
        }else{
            return call_user_func($callable,...$params);
        }
    }

    public static function callUserFuncArray(callable $callable,array $params)
    {
        if(SWOOLE_VERSION > 1){
            if($callable instanceof \Closure){
                return $callable(...$params);
            }else if(is_array($callable) && is_object($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class->$method(...$params);
            }else if(is_array($callable) && is_string($callable[0])){
                $class = $callable[0];
                $method = $callable[1];
                return $class::$method(...$params);
            }else if(is_string($callable)){
                return $callable(...$params);
            }else{
                return null;
            }
        }else{
            return call_user_func_array($callable,$params);
        }
    }
}