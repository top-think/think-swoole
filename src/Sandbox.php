<?php

namespace think\swoole;

use Closure;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\Http;
use think\swoole\concerns\ModifyProperty;
use think\swoole\contract\ResetterInterface;
use think\swoole\coroutine\Context;
use think\swoole\resetters\ClearInstances;
use think\swoole\resetters\ResetConfig;
use think\swoole\resetters\ResetEvent;
use think\swoole\resetters\ResetService;
use Throwable;
use think\swoole\App as SwooleApp;

class Sandbox
{
    use ModifyProperty;

    /**
     * The app containers in different coroutine environment.
     *
     * @var SwooleApp[]
     */
    protected $snapshots = [];

    /** @var SwooleApp */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    /** @var ResetterInterface[] */
    protected $resetters = [];
    protected $services  = [];

    public function __construct(Container $app)
    {
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

        return $this;
    }

    public function run(Closure $callable, $fd = null, $persistent = false)
    {
        $this->init($fd);

        try {
            $this->getApplication()->invoke($callable, [$this]);
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $this->clear(!$persistent);
        }
    }

    public function init($fd = null)
    {
        if (!is_null($fd)) {
            Context::setData('_fd', $fd);
        }
        $app = $this->getApplication(true);
        $this->setInstance($app);
        $this->resetApp($app);
    }

    public function clear($snapshot = true)
    {
        if ($snapshot && $this->getSnapshot()) {
            unset($this->snapshots[$this->getSnapshotId()]);

            // 垃圾回收
            $divisor     = $this->config->get('swoole.gc.divisor', 100);
            $probability = $this->config->get('swoole.gc.probability', 1);
            if (random_int(1, $divisor) <= $probability) {
                gc_collect_cycles();
            }
        }

        Context::clear();
        $this->setInstance($this->getBaseApp());
    }

    public function getApplication($init = false)
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }

        if ($init) {
            $snapshot = clone $this->getBaseApp();
            $this->setSnapshot($snapshot);

            return $snapshot;
        }
        throw new InvalidArgumentException('The app object has not been initialized');
    }

    protected function getSnapshotId()
    {
        if ($fd = Context::getData('_fd')) {
            return 'fd_' . $fd;
        }

        return Context::getCoroutineId();
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

        $reflectObject   = new ReflectionObject($app);
        $reflectProperty = $reflectObject->getProperty('services');
        $reflectProperty->setAccessible(true);
        $services = $reflectProperty->getValue($app);

        foreach ($services as $service) {
            $this->modifyProperty($service, $app);
        }
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

        $services = $this->config->get('swoole.services', []);

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

}
