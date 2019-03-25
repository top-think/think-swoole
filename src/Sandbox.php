<?php

namespace think\swoole;

use RuntimeException;
use think\Config;
use think\Container;
use think\Request;
use think\swoole\coroutine\Context;
use think\swoole\resetters\ResetterContract;

class Sandbox
{
    /** @var Container */
    protected $app;

    /** @var Config */
    protected $config;

    /**
     * @var array
     */
    protected $providers = [];

    protected $resetters = [];

    public function __construct($app = null)
    {
        if (!$app instanceof Container) {
            return;
        }

        $this->setBaseApp($app);
        $this->initialize();
    }

    public function setBaseApp(Container $app)
    {
        $this->app = $app;

        return $this;
    }

    public function getBaseApp()
    {
        return $this->app;
    }

    public function initialize()
    {
        if (!$this->app instanceof Container) {
            throw new RuntimeException('A base app has not been set.');
        }

        $this->setInitialConfig();
        $this->setInitialResetters();

        return $this;
    }

    public function run()
    {

    }

    protected function beforeRun()
    {
        if (!$this->config instanceof Config) {
            throw new RuntimeException('Please initialize after setting base app.');
        }

        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

    protected function afterRun()
    {
        Context::clear();
        $this->setInstance($this->getBaseApp());
    }

    public function getApplication()
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceOf Container) {
            return $snapshot;
        }

        $snapshot = clone $this->getBaseApp();
        $this->setSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * Get current snapshot.
     */
    public function getSnapshot()
    {
        return Context::getApp();
    }

    public function setSnapshot(Container $snapshot)
    {
        Context::setApp($snapshot);

        return $this;
    }

    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        Container::setInstance($app);
        Context::setApp($app);
    }

    public function handleStatic()
    {

    }

    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    /**
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get Initialized providers.
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app       = $this->getBaseApp();
        $resetters = $this->config->get('swoole.resetters', []);

        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterContract) {
                throw new RuntimeException("{$resetter} must implement " . ResetterContract::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Get Initialized resetters.
     */
    public function getResetters()
    {
        return $this->resetters;
    }

    /**
     * Reset Application.
     *
     * @param Container $app
     */
    public function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

    public function setRequest(Request $request)
    {
        Context::setData('_request', $request);

        return $this;
    }

    /**
     * Get current request.
     */
    public function getRequest()
    {
        return Context::getData('_request');
    }
}