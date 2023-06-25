<?php

namespace think\swoole\response;

use Psr\Http\Message\StreamInterface;
use think\Response;

class Stream extends Response
{

    protected $stream;
    protected $chunkSize;
    protected $readByLine;

    public function __construct(StreamInterface $stream, int $chunkSize = 1024, bool $readByLine = false)
    {
        $this->stream     = $stream->detach();
        $this->chunkSize  = $chunkSize;
        $this->readByLine = $readByLine;
    }

    public function eof()
    {
        return feof($this->stream);
    }

    public function read()
    {
        if ($this->readByLine) {
            return fgets($this->stream, $this->chunkSize);
        } else {
            return fread($this->stream, $this->chunkSize);
        }
    }

    public function __destruct()
    {
        fclose($this->stream);
        $this->stream = null;
    }

}
