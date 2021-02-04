<?php

namespace think\swoole\concerns;

use think\swoole\queue\Manager;

trait InteractsWithQueue
{
    public function prepareQueue()
    {
        if ($this->getConfig('queue.enable', false)) {
            /** @var Manager $queueManager */
            $queueManager = $this->container->make(Manager::class);

            $queueManager->attachToServer($this->getServer());
        }
    }
}
