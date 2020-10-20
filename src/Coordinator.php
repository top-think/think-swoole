<?php

namespace think\swoole;

use Swoole\Coroutine\Channel;

class Coordinator
{
    /**
     * @var Channel[]
     */
    private $channel = [];

    public function waitEvent($event, $timeout = -1): bool
    {
        $channel = $this->getChannel($event);

        $channel->pop((float) $timeout);
        return $channel->errCode === SWOOLE_CHANNEL_CLOSED;
    }

    public function triggerEvent($event): void
    {
        $channel = $this->getChannel($event);
        $channel->close();
    }

    private function getChannel($name)
    {
        if (empty($this->channel[$name])) {
            $this->channel[$name] = new Channel(1);
        }

        return $this->channel[$name];
    }
}
