<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/8
 * Email:499873958@qq.com
 */

namespace think\swoole\helper;

use ReflectionClass;
use think\App;
use think\swoole\interfaces\TimerInterface;
use XCron\CronExpression;

class Timer
{
    private $build;
    private $classes;
    private $app;
    private $timerLists;
    
    public function __construct(Build $build, App $app)
    {
        $this->build      = $build;
        $this->app        = $app;
        $this->classes    = [];
        $this->timerLists = [];
        $this->parse();
    }
    
    public function parse()
    {
        $classes = $this->getClasses();
        foreach ($classes as $class) {
            $cls      = $this->app->make($class);
            $interval = $cls->getInterval();
            if (is_string($interval)) {
                $cron               = CronExpression::factory($interval);
                $timer['nexttime']  = $cron->getNextRunDate()->getTimestamp();
                $timer['classObj']  = $cls;
                $timer['className'] = $class;
                $this->timerLists[] = $timer;
            }
            if (is_int($interval)) {
                $timer['nexttime']  = time() + $interval;
                $timer['classObj']  = $cls;
                $timer['className'] = $class;
                $this->timerLists[] = $timer;
            }
        }
        return $this->timerLists;
    }
    
    public function getClasses()
    {
        $classes = $this->build->getNameSpaceClasses();
        foreach ($classes as $one) {
            $ReflectionClass = new ReflectionClass($one);
            $interfaces      = $ReflectionClass->getInterfaces();
            if (isset($interfaces[TimerInterface::class])) {
                $this->classes[$one] = $one;
            }
        }
        return $this->classes;
    }
    
    public function run($class, $server)
    {
        if (is_string($class) && class_exists($class)) {
            $cls = new $class();
            $cls->run($server);
        }
    }
    
    public function getTimerLists()
    {
        return $this->timerLists;
    }
}
