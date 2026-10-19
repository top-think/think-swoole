<?php

namespace think\swoole\response;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use function fclose;
use function feof;
use function fread;
use function get_resource_type;
use function is_resource;
use function strpbrk;
use function stream_get_meta_data;

class Stream extends Iterator
{
    protected $stream;
    protected $chunkSize;
    protected $autoClose;

    protected $header = [
        'Content-Type' => 'application/octet-stream',
    ];

    public function __construct($stream, int $chunkSize = 8192, bool $autoClose = true)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('Stream must be a valid resource.');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be greater than zero.');
        }

        $metadata = stream_get_meta_data($stream);
        if (strpbrk($metadata['mode'], 'r+') === false) {
            throw new InvalidArgumentException('Stream must be readable.');
        }

        $this->stream    = $stream;
        $this->chunkSize = $chunkSize;
        $this->autoClose = $autoClose;

        parent::__construct($this->read());
    }

    public function getStream()
    {
        return $this->stream;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function close(): void
    {
        if ($this->autoClose && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function read(): Generator
    {
        try {
            while (!feof($this->stream)) {
                $content = fread($this->stream, $this->chunkSize);

                if ($content === false) {
                    throw new RuntimeException('Unable to read stream.');
                }

                if ($content === '') {
                    if (feof($this->stream)) {
                        break;
                    }

                    continue;
                }

                if ((yield $content) === false) {
                    break;
                }
            }
        } finally {
            $this->close();
        }
    }
}
