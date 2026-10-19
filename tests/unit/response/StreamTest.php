<?php

use think\swoole\response\Stream;

it('streams a resource in chunks', function () {
    $resource = fopen('php://temp', 'r+');
    fwrite($resource, 'abcdefgh');
    rewind($resource);

    $response = new Stream($resource, 3, false);
    $content  = '';

    foreach ($response->getIterator() as $chunk) {
        $content .= $chunk;
    }

    expect($content)->toBe('abcdefgh')
        ->and(is_resource($resource))->toBeTrue();

    fclose($resource);
});

it('closes the resource after streaming by default', function () {
    $resource = fopen('php://temp', 'r+');
    fwrite($resource, 'content');
    rewind($resource);

    $response = new Stream($resource);
    $content  = '';

    foreach ($response->getIterator() as $chunk) {
        $content .= $chunk;
    }

    expect($content)->toBe('content')
        ->and(is_resource($resource))->toBeFalse();
});

it('rejects an invalid stream resource', function () {
    new Stream('invalid');
})->throws(InvalidArgumentException::class, 'Stream must be a valid resource.');

it('rejects a non-positive chunk size', function () {
    $resource = fopen('php://temp', 'r+');

    try {
        new Stream($resource, 0);
    } finally {
        fclose($resource);
    }
})->throws(InvalidArgumentException::class, 'Chunk size must be greater than zero.');
