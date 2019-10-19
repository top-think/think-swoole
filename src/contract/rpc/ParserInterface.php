<?php

namespace think\swoole\contract\rpc;

use think\swoole\rpc\Protocol;

interface ParserInterface
{

    const EOF = "\r\n\r\n";

    /**
     * @param Protocol $protocol
     *
     * @return string
     */
    public function encode(Protocol $protocol): string;

    /**
     * @param string $string
     *
     * @return Protocol
     */
    public function decode(string $string): Protocol;

    /**
     * @param string $string
     *
     * @return mixed
     */
    public function decodeResponse(string $string);

    /**
     * @param mixed $result
     *
     * @return string
     */
    public function encodeResponse($result): string;
}
