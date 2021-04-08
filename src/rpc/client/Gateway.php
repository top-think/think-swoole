<?php

namespace think\swoole\rpc\client;

use Closure;
use Swoole\Coroutine\Client;
use think\File;
use think\helper\Arr;
use think\swoole\concerns\InteractsWithRpcConnector;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\exception\RpcClientException;
use think\swoole\exception\RpcResponseException;
use think\swoole\rpc\Error;
use think\swoole\rpc\Packer;
use think\swoole\rpc\Protocol;
use think\swoole\rpc\Sendfile;

class Gateway
{
    use Sendfile;

    /** @var Connector */
    protected $connector;

    /** @var ParserInterface */
    protected $parser;

    /**
     * Gateway constructor.
     * @param Connector|array $connector
     * @param ParserInterface $parser
     */
    public function __construct($connector, ParserInterface $parser)
    {
        if (is_array($connector)) {
            $connector = $this->createDefaultConnector($connector);
        }
        $this->connector = $connector;
        $this->parser    = $parser;
    }

    protected function encodeData(Protocol $protocol)
    {
        $params = $protocol->getParams();

        //有文件,先传输
        foreach ($params as $index => $param) {
            if ($param instanceof File) {
                yield from $this->fread($param);
                $params[$index] = Protocol::FILE;
            }
        }

        $protocol->setParams($params);

        $data = $this->parser->encode($protocol);

        yield Packer::pack($data);
    }

    protected function decodeResponse($response)
    {
        $result = $this->parser->decodeResponse($response);

        if ($result instanceof Error) {
            throw new RpcResponseException($result);
        }

        return $result;
    }

    protected function sendAndRecv($data)
    {
        return $this->connector->sendAndRecv($data, Closure::fromCallable([$this, 'decodeResponse']));
    }

    public function call(Protocol $protocol)
    {
        return $this->sendAndRecv($this->encodeData($protocol));
    }

    public function getServices()
    {
        return $this->sendAndRecv(Packer::pack(Protocol::ACTION_INTERFACE));
    }

    protected function createDefaultConnector($config)
    {
        $client = new Client(SWOOLE_SOCK_TCP);

        $host    = Arr::pull($config, 'host');
        $port    = Arr::pull($config, 'port');
        $timeout = Arr::pull($config, 'timeout', 5);

        $client->set($config);

        if (!$client->connect($host, $port, $timeout)) {
            throw new RpcClientException(
                sprintf('Connect failed host=%s port=%d', $host, $port)
            );
        }

        return new class($client) implements Connector {

            use InteractsWithRpcConnector;

            protected $client;

            /**
             *  constructor.
             * @param Client $client
             */
            public function __construct($client)
            {
                $this->client = $client;
            }

            protected function runWithClient($callback)
            {
                return $callback($this->client);
            }
        };
    }
}
