<?php
/**
 * Author:Xavier Yang
 * Date:2019/6/8
 * Email:499873958@qq.com
 */

namespace think\swoole\helper;


use think\App;

class Build
{
    private $baseNameSpace;
    private $appPath;
    private $classes;
    
    public function __construct(App $app)
    {
        $this->baseNameSpace = $app->getNamespace();
        $this->appPath       = $app->getAppPath();
        $this->classes       = [];
    }
    
    public function getClassFiles(): ?array
    {
        return glob($this->appPath . "*/*.php");
    }
    
    public function getNameSpaceClasses(): ?array
    {
        $classes = $this->getClassFiles();
        foreach ($classes as $one) {
            $one             = str_replace($this->appPath, "", $one);
            $one             = str_replace('.php', "", $one);
            $one             = str_replace('/', '\\', $one);
            $one             = $this->baseNameSpace .'\\'. $one;
            $this->classes[] = $one;
        }
        return $this->classes;
    }
    
    public function getClasses(): ?array
    {
        return $this->classes;
    }
}
