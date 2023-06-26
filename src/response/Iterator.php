<?php

namespace think\swoole\response;

use IteratorAggregate;
use think\Response;
use Traversable;

class Iterator extends Response implements IteratorAggregate
{
    protected $iterator;

    public function __construct(Traversable $iterator)
    {
        $this->iterator = $iterator;
    }

    public function getIterator()
    {
        return $this->iterator;
    }
}
