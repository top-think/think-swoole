<?php

namespace SwooleTW\Http\Concerns;

use think\App;
use think\Container;

/**
 * Trait WithApplication
 *
 * @property Container $container
 */
trait WithApplication
{
    /**
     * Application.
     *
     * @var App
     */
    protected $app;

    /**
     * Bootstrap app.
     */
    protected function bootstrap()
    {
        $bootstrappers = $this->getBootstrappers();
        $this->app->bootstrapWith($bootstrappers);

        $this->preResolveInstances();
    }

    /**
     * Load application.
     *
     * @return \Illuminate\Contracts\Foundation\Application
     */
    protected function loadApplication()
    {
        return require "{$this->basePath}/bootstrap/app.php";
    }

    /**
     * @return \Illuminate\Contracts\Container\Container|mixed
     * @throws \ReflectionException
     */
    public function getApplication()
    {
        if (!$this->app instanceof App) {
            $this->app = new App();
            $this->bootstrap();
        }

        return $this->app;
    }

    /**
     * Set laravel application.
     *
     * @param App $app
     */
    public function setApplication(App $app)
    {
        $this->app = $app;
    }


    /**
     * Reslove some instances before request.
     *
     * @throws \ReflectionException
     */
    protected function preResolveInstances()
    {
        $resolves = $this->container->make('config')->get('swoole.pre_resolved', []);

        foreach ($resolves as $abstract) {
            if ($this->getApplication()->offsetExists($abstract)) {
                $this->getApplication()->make($abstract);
            }
        }
    }

    /**
     * Get bootstrappers.
     *
     * @return array
     * @throws \ReflectionException
     */
    protected function getBootstrappers()
    {
        $kernel = $this->getApplication()->make(Kernel::class);

        $reflection          = new \ReflectionObject($kernel);
        $bootstrappersMethod = $reflection->getMethod('bootstrappers');
        $bootstrappersMethod->setAccessible(true);
        $bootstrappers = $bootstrappersMethod->invoke($kernel);

        array_splice($bootstrappers, -2, 0, ['Illuminate\Foundation\Bootstrap\SetRequestForConsole']);

        return $bootstrappers;
    }
}
