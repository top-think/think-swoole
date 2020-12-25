<?php

namespace think\swoole\rpc\server;

use RuntimeException;
use Swoole\Coroutine;
use think\swoole\rpc\Packer;
use think\swoole\rpc\server\channel\Buffer;
use think\swoole\rpc\server\channel\File;

class Channel
{
    protected $header;

    protected $queue;

    public function __construct($header)
    {
        switch ($header['type']) {
            case Packer::TYPE_BUFFER:
                $type = Buffer::class;
                break;
            case Packer::TYPE_FILE:
                $type = File::class;
                break;
            default:
                throw new RuntimeException("unsupported data type: [{$header['type']}");
        }

        $this->header = $header;
        $this->queue  = new Coroutine\Channel(1);

        Coroutine::create(function () use ($type, $header) {
            $handle = new $type($header['length']);
            $this->queue->push($handle);
        });
    }

    /**
     * @return File|Buffer
     */
    public function pop()
    {
        return $this->queue->pop();
    }

    public function push($handle)
    {
        return $this->queue->push($handle);
    }

    public function close()
    {
        return $this->queue->close();
    }

}
