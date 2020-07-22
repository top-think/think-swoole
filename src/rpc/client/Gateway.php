<?php

namespace think\swoole\rpc\client;

use RuntimeException;
use Swoole\Client;
use Swoole\Coroutine;
use think\File;
use think\helper\Arr;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\exception\RpcClientException;
use think\swoole\exception\RpcResponseException;
use think\swoole\rpc\Error;
use think\swoole\rpc\Packer;
use think\swoole\rpc\Protocol;

class Gateway
{
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
                $handle = fopen($param->getPathname(), "rb");
                yield pack(Packer::HEADER_PACK, $param->getSize(), Packer::TYPE_FILE);
                while (!feof($handle)) {
                    yield fread($handle, 8192);
                }
                fclose($handle);
                $params[$index] = Protocol::FILE;
            }
        }

        $protocol = Protocol::make($protocol->getInterface(), $protocol->getMethod(), $params);

        $data = $this->parser->encode($protocol);

        yield Packer::pack($data);
    }

    protected function decodeResponse($response)
    {
        [, $response] = Packer::unpack($response);
        $result = $this->parser->decodeResponse($response);

        if ($result instanceof Error) {
            throw new RpcResponseException($result);
        }

        return $result;
    }

    public function sendAndRecv(Protocol $protocol)
    {
        $response = $this->connector->sendAndRecv($this->encodeData($protocol));

        return $this->decodeResponse($response);
    }

    public function getServices()
    {
        $response = $this->connector->sendAndRecv(Packer::pack(Protocol::ACTION_INTERFACE));

        return $this->decodeResponse($response);
    }

    protected function createDefaultConnector($config)
    {
        $class = Coroutine::getCid() > -1 ? Coroutine\Client::class : Client::class;

        /** @var Client|Coroutine\Client $client */
        $client = new $class(SWOOLE_SOCK_TCP);

        $host    = Arr::pull($config, 'host');
        $port    = Arr::pull($config, 'port');
        $timeout = Arr::pull($config, 'timeout');

        $client->set(array_merge($config, [
            'open_length_check'     => true,
            'package_length_type'   => Packer::HEADER_PACK,
            'package_length_offset' => 0,
            'package_body_offset'   => 8,
        ]));

        if (!$client->connect($host, $port, $timeout)) {
            throw new RuntimeException(
                sprintf('Connect failed host=%s port=%d', $host, $port)
            );
        }

        return new class($client) implements Connector {
            protected $client;

            /**
             *  constructor.
             * @param Client|Coroutine\Client $client
             */
            public function __construct($client)
            {
                $this->client = $client;
            }

            public function sendAndRecv($data)
            {
                if (!$data instanceof \Generator) {
                    $data = [$data];
                }

                foreach ($data as $string) {
                    if (!$this->client->send($string)) {
                        throw new RpcClientException(swoole_strerror($this->client->errCode), $this->client->errCode);
                    }
                }

                $response = $this->client->recv();

                if ($response === false || empty($response)) {
                    throw new RpcClientException(swoole_strerror($this->client->errCode), $this->client->errCode);
                }

                return $response;
            }
        };
    }

}
