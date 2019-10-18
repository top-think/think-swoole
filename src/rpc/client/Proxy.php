<?php

namespace think\swoole\rpc\client;

use InvalidArgumentException;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\PhpFile;
use ReflectionClass;
use RuntimeException;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\exception\RpcResponseException;
use think\swoole\rpc\Error;
use think\swoole\rpc\Protocol;

class Proxy
{
    protected $client;
    protected $interface;

    protected $pool;
    protected $parser;

    public function __construct(Pool $pool, ParserInterface $parser)
    {
        $this->pool   = $pool;
        $this->parser = $parser;
    }

    protected function proxyCall($method, $params)
    {
        $protocol = Protocol::make($this->interface, $method, $params);
        $data     = $this->parser->encode($protocol);

        $client = $this->pool->connect($this->client);

        try {
            $response = $client->sendAndRecv($data);

            $client->returnToPool();

            $result = $this->parser->decodeResponse($response);

            if ($result instanceof Error) {
                throw new RpcResponseException($result);
            }

            return $result;
        } finally {
            $client->returnToPool();
        }
    }

    public static function getClassName($interface)
    {
        if (!interface_exists($interface)) {
            throw new InvalidArgumentException(
                sprintf('%s must be exist interface!', $interface)
            );
        }

        $name      = constant($interface . "::RPC");
        $proxyName = class_basename($interface) . "Service";
        $className = "rpc\\service\\${name}\\{$proxyName}";

        if (!class_exists($className, false)) {
            $file      = new PhpFile;
            $namespace = $file->addNamespace("rpc\\service\\${name}");
            $namespace->addUse(Proxy::class);
            $namespace->addUse($interface);

            $class = $namespace->addClass($proxyName);

            $class->setExtends(Proxy::class);
            $class->addImplement($interface);
            $class->addProperty('client', $name);
            $class->addProperty('interface', class_basename($interface));

            $reflection = new ReflectionClass($interface);

            foreach ($reflection->getMethods() as $methodRef) {
                $method = (new Factory)->fromMethodReflection($methodRef);
                $method->setBody("return \$this->proxyCall('{$methodRef->getName()}', func_get_args());");
                $class->addMember($method);
            }

            if (function_exists('eval')) {
                eval($file);
            } else {
                $proxyFile = sprintf('%s/%s.php', sys_get_temp_dir(), $proxyName);
                $result    = file_put_contents($proxyFile, $file);
                if ($result === false) {
                    throw new RuntimeException(sprintf('Proxy file(%s) generate fail', $proxyFile));
                }
                require $proxyFile;
                unlink($proxyFile);
            }
        }
        return $className;
    }
}
