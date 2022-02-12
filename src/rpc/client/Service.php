<?php

namespace think\swoole\rpc\client;

interface Service
{
    public function withContext($context): self;
}
