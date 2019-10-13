<?php

namespace think\swoole;

use Closure;
use RuntimeException;
use Symfony\Component\VarDumper\VarDumper;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\Http;
use think\service\PaginatorService;
use think\swoole\contract\ResetterInterface;
use think\swoole\coroutine\Context;
use think\swoole\middleware\ResetVarDumper;
use think\swoole\resetters\ClearInstances;
use think\swoole\resetters\ResetConfig;
use think\swoole\resetters\ResetEvent;
use think\swoole\resetters\ResetService;
use Throwable;

class Sandbox
{
    /**
     * The app containers in different coroutine environment.
     *
     * @var array
     */
    protected $snapshots = [];

    /** @var Manager */
    protected $manager;

    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    /** @var ResetterInterface[] */
    protected $resetters = [];
    protected $services  = [];

    public function __construct(Container $app, Manager $manager)
    {
        $this->manager = $manager;
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

    protected function initialize()
    {
        Container::setInstance(function () {
            return $this->getApplication();
        });

        $this->app->bind(Http::class, \think\swoole\Http::class);

        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();
        //兼容var-dumper
        $this->compatibleVarDumper();

        return $this;
    }

    public function run(Closure $callable, $fd = null, $persistent = false)
    {
        $this->init($fd);

        try {
            $this->getApplication()->invoke($callable, [$this]);
        } catch (Throwable $e) {
            $this->manager->logServerError($e);
        } finally {
            $this->clear(!$persistent);
        }
    }

    public function init($fd = null)
    {
        if (!is_null($fd)) {
            Context::setData('_fd', $fd);
        }
        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

    public function clear($snapshot = true)
    {
        if ($snapshot) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }

        Context::clear();
        $this->setInstance($this->getBaseApp());
        gc_collect_cycles();
    }

    public function getApplication()
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }

        $snapshot = clone $this->getBaseApp();
        $this->setSnapshot($snapshot);

        return $snapshot;
    }

    protected function getSnapshotId()
    {
        if ($fd = Context::getData('_fd')) {
            return "fd_" . $fd;
        } else {
            return Context::getCoroutineId();
        }
    }

    /**
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot()
    {
        return $this->snapshots[$this->getSnapshotId()] ?? null;
    }

    public function setSnapshot(Container $snapshot)
    {
        $this->snapshots[$this->getSnapshotId()] = $snapshot;

        return $this;
    }

    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);
    }

    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function getServices()
    {
        return $this->services;
    }

    protected function setInitialServices()
    {
        $app = $this->getBaseApp();

        $services = [
            PaginatorService::class,
        ];

        $services = array_merge($services, $this->config->get('swoole.services', []));

        foreach ($services as $service) {
            if (class_exists($service) && !in_array($service, $this->services)) {
                $serviceObj               = new $service($app);
                $this->services[$service] = $serviceObj;
            }
        }
    }

    /**
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
            ResetConfig::class,
            ResetEvent::class,
            ResetService::class,
        ];

        $resetters = array_merge($resetters, $this->config->get('swoole.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterInterface) {
                throw new RuntimeException("{$resetter} must implement " . ResetterInterface::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Reset Application.
     *
     * @param Container $app
     */
    protected function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

    protected function compatibleVarDumper()
    {
        if (class_exists(VarDumper::class)) {
            $this->app->middleware->add(ResetVarDumper::class);
        }
    }

}
