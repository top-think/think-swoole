<?php

namespace think\swoole\rpc\client;

use InvalidArgumentException;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\PhpNamespace;
use ReflectionClass;
use think\swoole\rpc\Protocol;

abstract class Proxy
{
    protected $interface;

    /** @var Gateway */
    protected $gateway;

    final public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    final protected function proxyCall($method, $params)
    {
        $protocol = Protocol::make($this->interface, $method, $params);

        return $this->gateway->sendAndRecv($protocol);
    }

    final public static function getClassName($client, $interface)
    {
        if (!interface_exists($interface)) {
            throw new InvalidArgumentException(
                sprintf('%s must be exist interface!', $interface)
            );
        }

        $proxyName = class_basename($interface) . "Service";
        $className = "rpc\\service\\{$client}\\{$proxyName}";

        if (!class_exists($className, false)) {

            $namespace = new PhpNamespace("rpc\\service\\{$client}");
            $namespace->addUse(Proxy::class);
            $namespace->addUse($interface);

            $class = $namespace->addClass($proxyName);

            $class->setExtends(Proxy::class);
            $class->addImplement($interface);
            $class->addProperty('interface', class_basename($interface));

            $reflection = new ReflectionClass($interface);

            foreach ($reflection->getMethods() as $methodRef) {
                $method = (new Factory)->fromMethodReflection($methodRef);
                $method->setBody("return \$this->proxyCall('{$methodRef->getName()}', func_get_args());");
                $class->addMember($method);
            }

            eval($namespace);
        }
        return $className;
    }
}
