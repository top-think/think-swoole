<?php

namespace think\swoole;

use think\swoole\coroutine\Context;

class App extends \think\App
{
	protected $coordinator=[];
	
    public function runningInConsole()
    {
        return Context::hasData('_fd');
    }

    public function clearInstances()
    {
        $this->instances = [];
    }
	
	public function getCoordinator(string $name)
	{
		if (!isset($this->coordinator[$name])) {
			$this->coordinator[$name] = new Coordinator();
		}
		
		return $this->coordinator[$name];
	}
}
