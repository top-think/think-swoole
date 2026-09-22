<?php

namespace think\swoole;

use Swoole\Coroutine as SwooleCoroutine;
use think\swoole\coroutine\Context;
use think\swoole\coroutine\Scope;

/**
 * 协程创建入口
 *
 * 除创建协程外，还把当前协程的两项归属信息传递给子协程：
 * 1. 沙箱归属（#root-id）：后代确定所属沙箱不再依赖父协程存活，见 Context::getRootId()
 * 2. 作用域（Scope）：子协程退出时归还计数，Scope 未清零前 root 协程不会结束
 *
 * 注意：归属信息只在创建动作里传递，只有经过本入口创建的协程才具有沙箱归属和作用域，
 * 裸 go()、Swoole\Timer 回调等由 Swoole 直接创建的协程属于"无归属协程"。
 */
class Coroutine
{
    /**
     * 创建协程
     * @param callable $fn
     * @param mixed ...$args
     * @return int|false 协程ID，创建失败返回 false
     */
    public static function create(callable $fn, ...$args)
    {
        $rootId = Context::getRootId();
        $scope  = Context::getScope($rootId);

        return SwooleCoroutine::create(function (...$args) use ($fn, $rootId, $scope) {
            if ($rootId > 0) {
                //绑定沙箱归属，避免后代向上查找时因父协程提前退出而断链
                Context::setRootId($rootId);
            }

            if ($scope instanceof Scope) {
                //子协程创建后优先执行，此处必然先于父协程继续执行
                $scope->enter();
            }

            try {
                $fn(...$args);
            } finally {
                if ($scope instanceof Scope) {
                    $scope->leave();
                }
            }
        }, ...$args);
    }
}
