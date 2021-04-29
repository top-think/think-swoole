<?php

namespace think\swoole\websocket;

use Swoole\Server;

/**
 * Class Pusher
 */
class Pusher
{
    /**
     * @var Server|\Swoole\WebSocket\Server
     */
    protected $server;

    /**
     * @var int
     */
    protected $sender;

    /**
     * @var array
     */
    protected $descriptors;

    /**
     * @var bool
     */
    protected $broadcast;

    /**
     * @var bool
     */
    protected $assigned;

    /**
     * @var string
     */
    protected $payload;

    /**
     * Push constructor.
     *
     * @param Server $server
     * @param int $sender
     * @param array $descriptors
     * @param bool $broadcast
     * @param bool $assigned
     * @param string $payload
     */
    public function __construct(
        Server $server,
        string $payload,
        int $sender = 0,
        array $descriptors = [],
        bool $broadcast = false,
        bool $assigned = false
    )
    {
        $this->sender      = $sender;
        $this->descriptors = $descriptors;
        $this->broadcast   = $broadcast;
        $this->assigned    = $assigned;
        $this->payload     = $payload;
        $this->server      = $server;
    }

    /**
     * @return int
     */
    public function getSender(): int
    {
        return $this->sender;
    }

    /**
     * @return array
     */
    public function getDescriptors(): array
    {
        return $this->descriptors;
    }

    /**
     * @param int $descriptor
     *
     * @return self
     */
    public function addDescriptor($descriptor): self
    {
        return $this->addDescriptors([$descriptor]);
    }

    /**
     * @param array $descriptors
     *
     * @return self
     */
    public function addDescriptors(array $descriptors): self
    {
        $this->descriptors = array_values(
            array_unique(
                array_merge($this->descriptors, $descriptors)
            )
        );

        return $this;
    }

    /**
     * @param int $descriptor
     *
     * @return bool
     */
    public function hasDescriptor(int $descriptor): bool
    {
        return in_array($descriptor, $this->descriptors);
    }

    /**
     * @return bool
     */
    public function isBroadcast(): bool
    {
        return $this->broadcast;
    }

    /**
     * @return bool
     */
    public function isAssigned(): bool
    {
        return $this->assigned;
    }

    /**
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        return $this->broadcast && empty($this->descriptors) && !$this->assigned;
    }

    /**
     * Returns all descriptors that are websocket
     *
     * @return array
     */
    protected function getWebsocketConnections(): array
    {
        return array_filter(iterator_to_array($this->server->connections), function ($fd) {
            return (bool) ($this->server->getClientInfo($fd)['websocket_status'] ?? false);
        });
    }

    /**
     * @param int $fd
     *
     * @return bool
     */
    protected function shouldPushToDescriptor(int $fd): bool
    {
        if (!$this->server->isEstablished($fd)) {
            return false;
        }

        return !$this->broadcast || $this->sender !== (int) $fd;
    }

    /**
     * Push message to related descriptors
     * @return void
     */
    public function push(): void
    {
        // attach sender if not broadcast
        if (!$this->broadcast && $this->sender && !$this->hasDescriptor($this->sender)) {
            $this->addDescriptor($this->sender);
        }

        // check if to broadcast to other clients
        if ($this->shouldBroadcast()) {
            $this->addDescriptors($this->getWebsocketConnections());
        }

        // push message to designated fds
        foreach ($this->descriptors as $descriptor) {
            if ($this->shouldPushToDescriptor($descriptor)) {
                $this->server->push($descriptor, $this->payload);
            }
        }
    }
}
