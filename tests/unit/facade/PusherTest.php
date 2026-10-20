<?php

use think\Container;
use think\swoole\facade\Pusher as PusherFacade;
use think\swoole\websocket\Pusher;

it('proxies static calls to independent pusher instances', function () {
    $app       = app();
    $container = new Container();

    $container->bind(Pusher::class, function () {
        return new class {
            public $targets = [];

            public function to(...$values)
            {
                $this->targets = $values;

                return $this;
            }
        };
    });

    Container::setInstance($container);

    try {
        $first  = PusherFacade::to('room-a');
        $second = PusherFacade::to('room-b');

        expect($first)
            ->not->toBe($second)
            ->and($first->targets)->toBe(['room-a'])
            ->and($second->targets)->toBe(['room-b']);
    } finally {
        Container::setInstance($app);
    }
});
