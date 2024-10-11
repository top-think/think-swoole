<?php

namespace think\swoole\concerns;

trait WithMiddleware
{
    /**
     * 中间件
     * @var array
     */
    protected $middleware = [];

    protected function middleware($middleware, ...$params)
    {
        $options = [];

        $this->middleware[] = [
            'middleware' => [$middleware, $params],
            'options' => &$options,
        ];

        return new class($options) {
            protected $options;

            public function __construct(array &$options)
            {
                $this->options = &$options;
            }

            public function only($methods)
            {
                $this->options['only'] = is_array($methods) ? $methods : func_get_args();
                return $this;
            }

            public function except($methods)
            {
                $this->options['except'] = is_array($methods) ? $methods : func_get_args();

                return $this;
            }
        };
    }
}
