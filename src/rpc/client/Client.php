<?php

namespace think\swoole\rpc\client;

use think\swoole\contract\rpc\ParserInterface;
use think\swoole\exception\RpcClientException;

/**
 * Class Client
 * @package think\swoole\rpc\client
 */
class Client
{
    protected $host;
    protected $port;
    protected $timeout;
    protected $options;

    /** @var \Swoole\Coroutine\Client */
    protected $handler;

    public function __construct($host, $port, $timeout = 0.5, $options = [])
    {
        $this->host    = $host;
        $this->port    = $port;
        $this->timeout = $timeout;
        $this->options = $options;
        $this->connect();
    }

    public function sendAndRecv(string $data, bool $reconnect = false)
    {
        if ($reconnect) {
            $this->connect();
        }

        try {
            if (!$this->send($data)) {
                throw new RpcClientException(swoole_strerror($this->handler->errCode), $this->handler->errCode);
            }

            $result = $this->handler->recv();

            if ($result === false || empty($result)) {
                throw new RpcClientException(swoole_strerror($this->handler->errCode), $this->handler->errCode);
            }

            return $result;
        } catch (RpcClientException $e) {
            if ($reconnect) {
                throw  $e;
            }
            return $this->sendAndRecv($data, true);
        }
    }

    public function send($data)
    {
        return $this->handler->send($data . ParserInterface::EOF);
    }

    protected function connect()
    {
        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);

        $client->set([
            'open_eof_check' => true,
            'open_eof_split' => true,
            'package_eof'    => ParserInterface::EOF,
        ]);

        if (!$client->connect($this->host, $this->port, $this->timeout)) {
            throw new RpcClientException(
                sprintf('Connect failed host=%s port=%d', $this->host, $this->port)
            );
        }

        $this->handler = $client;
    }

    protected function __destruct()
    {
        if ($this->handler) {
            $this->handler->close();
        }
    }
}
