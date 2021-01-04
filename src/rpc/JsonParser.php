<?php

namespace think\swoole\rpc;

use Exception;
use think\swoole\contract\rpc\ParserInterface;

class JsonParser implements ParserInterface
{
    /**
     * Json-rpc version
     */
    const VERSION = '2.0';

    const DELIMITER = '@';

    /**
     * @param Protocol $protocol
     *
     * @return string
     */
    public function encode(Protocol $protocol): string
    {
        $interface  = $protocol->getInterface();
        $methodName = $protocol->getMethod();

        $method = $interface . self::DELIMITER . $methodName;
        $data   = [
            'jsonrpc' => self::VERSION,
            'method'  => $method,
            'params'  => $protocol->getParams(),
            'id'      => '',
        ];

        $string = json_encode($data, JSON_UNESCAPED_UNICODE);

        return $string;
    }

    /**
     * @param string $string
     *
     * @return Protocol
     */
    public function decode(string $string): Protocol
    {
        $data = json_decode($string, true);

        $error = json_last_error();
        if ($error != JSON_ERROR_NONE) {
            throw new Exception(
                sprintf('Data(%s) is not json format!', $string)
            );
        }

        $method = $data['method'] ?? '';
        $params = $data['params'] ?? [];

        if (empty($method)) {
            throw new Exception(
                sprintf('Method(%s) cant not be empty!', $string)
            );
        }

        $methodAry = explode(self::DELIMITER, $method);
        if (count($methodAry) < 2) {
            throw new Exception(
                sprintf('Method(%s) is bad format!', $method)
            );
        }

        [$interfaceClass, $methodName] = $methodAry;

        if (empty($interfaceClass) || empty($methodName)) {
            throw new Exception(
                sprintf('Interface(%s) or Method(%s) can not be empty!', $interfaceClass, $method)
            );
        }

        return Protocol::make($interfaceClass, $methodName, $params);
    }

    /**
     * @param string $string
     *
     * @return mixed
     */
    public function decodeResponse(string $string)
    {
        $data = json_decode($string, true);

        if (array_key_exists('result', $data)) {
            return $data['result'];
        }

        $code    = $data['error']['code'] ?? 0;
        $message = $data['error']['message'] ?? '';
        $data    = $data['error']['data'] ?? null;

        return Error::make($code, $message, $data);
    }

    /**
     * @param mixed $result
     *
     * @return string
     */
    public function encodeResponse($result): string
    {
        $data = [
            'jsonrpc' => self::VERSION,
        ];

        if ($result instanceof Error) {
            $data['error'] = $result;
        } else {
            $data['result'] = $result;
        }

        $string = json_encode($data);

        return $string;
    }
}
