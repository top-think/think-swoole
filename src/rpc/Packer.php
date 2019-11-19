<?php

namespace think\swoole\rpc;

class Packer
{
    const HEADER_SIZE   = 8;
    const HEADER_STRUCT = "Nlength/Ntype";
    const HEADER_PACK   = "NN";

    const TYPE_BUFFER = 0;
    const TYPE_FILE   = 1;

    public static function pack($data, $type = self::TYPE_BUFFER)
    {
        return pack(self::HEADER_PACK, strlen($data), $type) . $data;
    }

    public static function unpack($data)
    {
        $header = unpack(self::HEADER_STRUCT, substr($data, 0, self::HEADER_SIZE));
        $data   = substr($data, self::HEADER_SIZE);

        return [$header, $data];
    }
}
