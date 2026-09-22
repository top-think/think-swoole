<?php

namespace think\swoole;

use Closure;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;
use Swoole\Timer;
use think\Config;
use think\Container;
use think\Event;
use think\exception\Handle;
use think\Log;
use think\swoole\concerns\ModifyProperty;
use think\swoole\contract\ResetterInterface;
use think\swoole\coroutine\Context;
use think\swoole\coroutine\Scope;
use think\swoole\resetters\ClearInstances;
use think\swoole\resetters\ResetConfig;
use think\swoole\resetters\ResetEvent;
use think\swoole\resetters\ResetModel;
use think\swoole\resetters\ResetPaginator;
use think\swoole\resetters\ResetService;
use Throwable;

class Sandbox
{
    use ModifyProperty;

    /** @var App[] */
    protected $snapshots = [];

    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    /** @var ResetterInterface[] */
    protected $resetters = [];
    protected $services  = [];

    public function __construct(App $app)
    {
        $this->setBaseApp($app);
        $this->initialize();
    }

    public function setBaseApp(App $app)
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

        $this->setInitialConfig();
        $this->setInitialServices();
        $this->setInitialEvent();
        $this->setInitialResetters();

        return $this;
    }

    public function run(Closure $callable)
    {
        $this->init();

        $scope = new Scope();
        //作用域只保存在 root 上，后代协程通过 Context::getScope() 沿链解析
        Context::setScope($scope);

        $app = $this->getApplication();
        try {
            $app->invoke($callable, [$this]);
        } catch (Throwable $e) {
            $app->make(Handle::class)->report($e);
        } finally {
            //后代协程全部退出前 root 协程不会结束，沙箱在此期间保持有效
            $this->waitScope($scope, $app);

            $this->clear();
        }
    }

    /**
     * 等待所有后代协程退出
     * @param Scope $scope
     * @param App $app
     */
    protected function waitScope(Scope $scope, App $app)
    {
        $warning = (float) $this->getConfig()->get('swoole.scope.wait_warning', 30);

        //等待过久时输出告警，便于排查一直没有退出的后代协程
        $timer = $warning > 0
            ? Timer::after((int) ($warning * 1000), function () use ($app, $scope, $warning) {
                $app->make(Log::class)->warning(
                    "sandbox has waited {$warning}s: {$scope->getCount()} descendant coroutine(s) still running"
                );
            })
            : null;

        try {
            $scope->wait();
        } finally {
            if ($timer) {
                Timer::clear($timer);
            }
        }
    }

    public function init()
    {
        $app = $this->getApplication(true);
        $this->setInstance($app);
        $this->resetApp($app);
    }

    public function clear()
    {
        if ($app = $this->getSnapshot()) {
            $app->clearInstances();
            unset($this->snapshots[$this->getSnapshotId()]);
        }

        Context::clear();
        $this->setInstance($this->getBaseApp());
    }

    public function getApplication($init = false)
    {
        $snapshot = $this->getSnapshot($init);
        if ($snapshot instanceof App) {
            return $snapshot;
        }

        if ($init) {
            $snapshot = clone $this->getBaseApp();
            $this->setSnapshot($snapshot);

            return $snapshot;
        }
        throw new InvalidArgumentException('The app object has not been initialized');
    }

    protected function getSnapshotId($init = false)
    {
        return Context::getRootId($init);
    }

    /**
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot($init = false)
    {
        return $this->snapshots[$this->getSnapshotId($init)] ?? null;
    }

    public function setSnapshot(App $snapshot)
    {
        $this->snapshots[$this->getSnapshotId()] = $snapshot;

        return $this;
    }

    public function setInstance(App $app)
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
            ResetModel::class,
            ResetPaginator::class,
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
     * @param App $app
     */
    protected function resetApp(App $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
