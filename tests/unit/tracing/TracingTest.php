<?php

use think\swoole\Manager;

class TracingTestManager extends Manager
{
    public $workers = [];

    public $events = [];

    public $tracers = [];

    public function prepareTracingForTest(): void
    {
        $this->prepareTracing();
    }

    public function addWorker(callable $func, $name = null): self
    {
        $this->workers[] = [
            'callback' => $func,
            'name'     => $name,
        ];

        return $this;
    }

    public function onEvent(string $event, $listener, bool $first = false): void
    {
        $this->events[] = [
            'event'    => $event,
            'listener' => $listener,
        ];
    }

    protected function startTracingReporters(array $tracers)
    {
        $this->tracers = $tracers;
    }
}

beforeEach(function () {
    $this->tracingConfig = app()->config->get('tracing');
});

afterEach(function () {
    app()->config->set($this->tracingConfig, 'tracing');
});

it('runs all async tracers in one shared worker', function () {
    app()->config->set([
        'tracers' => [
            'zipkin' => ['async' => true],
            'jaeger' => ['async' => true],
            'noop'   => ['async' => false],
        ],
    ], 'tracing');

    $manager = new TracingTestManager(app());
    $manager->prepareTracingForTest();

    expect($manager->workers)->toHaveCount(1)
        ->and($manager->workers[0]['name'])->toBe('tracing [zipkin,jaeger]')
        ->and($manager->events)->toHaveCount(1)
        ->and($manager->events[0]['event'])->toBe('workerStart');

    ($manager->workers[0]['callback'])();

    expect($manager->tracers)->toBe(['zipkin', 'jaeger']);
});

it('does not create a tracing worker without async tracers', function () {
    app()->config->set([
        'tracers' => [
            'zipkin' => ['async' => false],
        ],
    ], 'tracing');

    $manager = new TracingTestManager(app());
    $manager->prepareTracingForTest();

    expect($manager->workers)->toBeEmpty()
        ->and($manager->events)->toBeEmpty();
});
