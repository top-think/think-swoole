<?php

namespace think\swoole\concerns;

use Generator;
use Swoole\Coroutine\Client;
use think\swoole\exception\RpcClientException;
use think\swoole\rpc\File;
use think\swoole\rpc\Packer;
use think\swoole\rpc\Protocol;

trait InteractsWithRpcConnector
{
    abstract protected function runWithClient($callback);

    protected function recv(Client $client, callable $decoder)
    {
        $handler = null;
        $file    = null;

        while ($data = $client->recv()) {
            begin:
            if (empty($handler)) {
                [$handler, $data] = Packer::unpack($data);
            }

            $response = $handler->write($data);

            if (!empty($response)) {
                $handler = null;

                if ($response instanceof File) {
                    $file = $response;
                } else {
                    $result = $decoder($response);
                    if ($result === Protocol::FILE) {
                        $result = $file;
                    }
                    return $result;
                }
            }

            if (!empty($data)) {
                goto begin;
            }
        }

        if ($data === '') {
            throw new RpcClientException('Connection is closed. ' . $client->errMsg, $client->errCode);
        }
        if ($data === false) {
            throw new RpcClientException('Error receiving data, errno=' . $client->errCode . ' errmsg=' . swoole_strerror($client->errCode), $client->errCode);
        }
    }

    public function sendAndRecv($data, callable $decoder)
    {
        if (!$data instanceof Generator) {
            $data = [$data];
        }

        return $this->runWithClient(function (Client $client) use ($decoder, $data) {
            try {
                foreach ($data as $string) {
                    if (!empty($string)) {
                        if ($client->send($string) === false) {
                            throw new RpcClientException('Send data failed. ' . $client->errMsg, $client->errCode);
                        }
                    }
                }
                return $this->recv($client, $decoder);
            } catch (RpcClientException $e) {
                $client->close();
                throw $e;
            }
        });
    }

}
