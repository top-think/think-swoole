<?php

namespace think\swoole\rpc\server;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Swoole\Coroutine\Server\Connection;
use think\App;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\Middleware;
use think\swoole\rpc\Error;
use think\swoole\rpc\Packer;
use think\swoole\rpc\Protocol;
use think\swoole\rpc\Sendfile;
use Throwable;

class Dispatcher
{
    use Sendfile;

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

    protected $parser;

    protected $services = [];

    protected $middleware = [];

    public function __construct(ParserInterface $parser, $services, $middleware = [])
    {
        $this->parser = $parser;
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
            if (!empty($interfaces)) {
                foreach ($interfaces as $interface) {
                    $this->services[class_basename($interface)] = [
                        'interface' => $interface,
                        'class'     => $className,
                    ];
                }
            } else {
                $this->services[class_basename($className)] = [
                    'interface' => $className,
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
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }
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

            if ($parameter->allowsNull()) {
                $param['nullable'] = true;
            }

            $parameters[] = $param;
        }
        return $parameters;
    }

    /**
     * 调度
     * @param App          $app
     * @param Connection   $conn
     * @param string|Error $data
     * @param array        $files
     */
    public function dispatch(App $app, Connection $conn, $data, $files = [])
    {
        try {
            switch (true) {
                case $data instanceof Error:
                    $result = $data;
                    break;
                case $data === Protocol::ACTION_INTERFACE:
                    $result = $this->getInterfaces();
                    break;
                default:
                    $protocol = $this->parser->decode($data);
                    $result   = $this->dispatchWithMiddleware($app, $protocol, $files);
            }
        } catch (Throwable $e) {
            $result = Error::make($e->getCode(), $e->getMessage());
        }

        //传输文件
        if ($result instanceof \think\File) {
            foreach ($this->fread($result) as $string) {
                if (!empty($string)) {
                    $conn->send($string);
                }
            }
            $result = Protocol::FILE;
        }

        $data = $this->parser->encodeResponse($result);

        $conn->send(Packer::pack($data));
    }

    protected function dispatchWithMiddleware(App $app, Protocol $protocol, $files)
    {
        $interface = $protocol->getInterface();
        $method    = $protocol->getMethod();
        $params    = $protocol->getParams();

        //文件参数
        foreach ($params as $index => $param) {
            if ($param === Protocol::FILE) {
                $params[$index] = array_shift($files);
            }
        }

        $service = $this->services[$interface] ?? null;
        if (empty($service)) {
            throw new RuntimeException(
                sprintf('Service %s is not founded!', $interface),
                self::METHOD_NOT_FOUND
            );
        }

        $instance    = $app->make($service['class']);
        $middlewares = array_merge($this->middleware, $this->getServiceMiddlewares($instance, $method));

        return Middleware::make($app, $middlewares)
                         ->pipeline()
                         ->send($protocol)
                         ->then(function () use ($instance, $method, $params) {
                             return call_user_func_array([$instance, $method], $params);
                         });
    }

    protected function getServiceMiddlewares($service, $method)
    {
        $middlewares = [];

        $class = new ReflectionClass($service);

        if ($class->hasProperty('middleware')) {
            $reflectionProperty = $class->getProperty('middleware');
            $reflectionProperty->setAccessible(true);

            foreach ($reflectionProperty->getValue($service) as $key => $val) {
                if (!is_int($key)) {
                    $middleware = $key;
                    $options    = $val;
                } elseif (isset($val['middleware'])) {
                    $middleware = $val['middleware'];
                    $options    = $val['options'] ?? [];
                } else {
                    $middleware = $val;
                    $options    = [];
                }

                if ((isset($options['only']) && !in_array($method, (array) $options['only'])) ||
                    (!empty($options['except']) && in_array($method, (array) $options['except']))) {
                    continue;
                }

                if (is_string($middleware) && strpos($middleware, ':')) {
                    $middleware = explode(':', $middleware);
                    if (count($middleware) > 1) {
                        $middleware = [$middleware[0], array_slice($middleware, 1)];
                    }
                }

                $middlewares[] = $middleware;
            }
        }

        return $middlewares;
    }
}
