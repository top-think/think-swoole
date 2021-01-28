<?php

namespace think\swoole\rpc\server;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Swoole\Server;
use think\App;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\Middleware;
use think\swoole\rpc\Error;
use think\swoole\rpc\File;
use think\swoole\rpc\Packer;
use think\swoole\rpc\Protocol;
use Throwable;

class Dispatcher
{
    /**
     * Parser error
     */
    const PARSER_ERROR = -32700;

    /**
     * Invalid Request
     */
    const INVALID_REQUEST = -32600;

    /**
     * Method not found
     */
    const METHOD_NOT_FOUND = -32601;

    /**
     * Invalid params
     */
    const INVALID_PARAMS = -32602;

    /**
     * Internal error
     */
    const INTERNAL_ERROR = -32603;

    protected $app;

    protected $parser;

    protected $services = [];

    protected $server;

    protected $files = [];

    protected $middleware = [];

    public function __construct(App $app, ParserInterface $parser, Server $server, $services, $middleware = [])
    {
        $this->app    = $app;
        $this->parser = $parser;
        $this->server = $server;
        $this->prepareServices($services);
        $this->middleware = $middleware;
    }

    /**
     * 获取服务接口
     * @param $services
     * @throws ReflectionException
     */
    protected function prepareServices($services)
    {
        foreach ($services as $className) {
            $reflectionClass = new ReflectionClass($className);
            $interfaces      = $reflectionClass->getInterfaceNames();

            foreach ($interfaces as $interface) {
                $this->services[class_basename($interface)] = [
                    'interface' => $interface,
                    'class'     => $className,
                ];
            }
        }
    }

    /**
     * 获取接口信息
     * @return array
     */
    protected function getInterfaces()
    {
        $interfaces = [];
        foreach ($this->services as $key => ['interface' => $interface]) {
            $interfaces[$key] = $this->getMethods($interface);
        }
        return $interfaces;
    }

    protected function getMethods($interface)
    {
        $methods = [];

        $reflection = new ReflectionClass($interface);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType) {
                $returnType = $returnType->getName();
            }
            $methods[$method->getName()] = [
                'parameters' => $this->getParameters($method),
                'returnType' => $returnType,
                'comment'    => $method->getDocComment(),
            ];
        }
        return $methods;
    }

    protected function getParameters(ReflectionMethod $method)
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType) {
                $type = $type->getName();
            }
            $param = [
                'name' => $parameter->getName(),
                'type' => $type,
            ];

            if ($parameter->isOptional()) {
                $param['default'] = $parameter->getDefaultValue();
            }

            $parameters[] = $param;
        }
        return $parameters;
    }

    /**
     * 调度
     * @param int $fd
     * @param string|File|Error $data
     */
    public function dispatch(int $fd, $data)
    {
        try {
            switch (true) {
                case $data instanceof File:
                    $this->files[$fd][] = $data;
                    return;
                case $data instanceof Error:
                    $result = $data;
                    break;
                case $data === Protocol::ACTION_INTERFACE:
                    $result = $this->getInterfaces();
                    break;
                default:
                    $protocol = $this->parser->decode($data);
                    $result   = $this->dispatchWithMiddleware($protocol, $fd);
            }
        } catch (Throwable $e) {
            $result = Error::make($e->getCode(), $e->getMessage());
        }

        $data = $this->parser->encodeResponse($result);

        $this->server->send($fd, Packer::pack($data));
        //清空文件缓存
        unset($this->files[$fd]);
    }

    protected function dispatchWithMiddleware(Protocol $protocol, $fd)
    {
        return Middleware::make($this->app, $this->middleware)
            ->pipeline()
            ->send($protocol)
            ->then(function (Protocol $protocol) use ($fd) {

                $interface = $protocol->getInterface();
                $method    = $protocol->getMethod();
                $params    = $protocol->getParams();

                //文件参数
                foreach ($params as $index => $param) {
                    if ($param === Protocol::FILE) {
                        $params[$index] = array_shift($this->files[$fd]);
                    }
                }

                $service = $this->services[$interface] ?? null;
                if (empty($service)) {
                    throw new RuntimeException(
                        sprintf('Service %s is not founded!', $interface),
                        self::METHOD_NOT_FOUND
                    );
                }

                return $this->app->invoke([$this->app->make($service['class']), $method], $params);
            });
    }
}
