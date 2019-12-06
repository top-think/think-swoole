<?php

namespace think\swoole\concerns;

use ReflectionObject;

trait ModifyProperty
{
    protected function modifyProperty($object, $value, $property = 'app')
    {
        $reflectObject = new ReflectionObject($object);
        if ($reflectObject->hasProperty($property)) {
            $reflectProperty = $reflectObject->getProperty($property);
            $reflectProperty->setAccessible(true);
            $reflectProperty->setValue($object, $value);
        }
    }
}
