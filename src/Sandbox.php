<?php

namespace think\swoole;

use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
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
    protected $ch;

    public function __construct(Container $app)
    {
        $this->ch = new Channel();
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
            try {
                return $this->getApplication();
            } catch (Throwable $e) { //协程逃逸处理
                try {
                    $cid = Coroutine::getCid();
                    $this->init();
                    return $this->snapshots[$cid];
                } finally { //防止逃逸snapshot内存泄漏
                    $this->clear();
                }
            }

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
        if ($snapshot && $s=$this->getSnapshot()) {
            /**
             * 增加动态超时时间控制.如果你觉得该请求会执行比较长的时间，在控制器入口处request()->gc_timeout=60,默认10s
             */
            $timeout = $s->make('request')->gc_timeout ?? 10;
            $this->ch->pop($timeout);
            unset($this->snapshots[$this->getSnapshotId()]);
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

    protected function getSnapshotId($init=false)
    {
        if ($fd = Context::getData('_fd')) {
            return 'fd_' . $fd;
        }
        if ($init) {
            Coroutine::getContext()->offsetSet('#root', true);
            return Coroutine::getCid();
        } else {
            $cid = Coroutine::getCid();
            while (!(Coroutine::getContext($cid)->offsetExists('#root'))) {
                $cid = Coroutine::getPcid($cid);
                if (Coroutine::getContext($cid) == null) {
                    throw new Exception("发现逃逸协程:$cid");
                }
                if ($cid < 1) {
                    break;
                }
            }
            return $cid;
        }

    }

    /**
     * Get current snapshot.
     * @return App|null
     */
    public function getSnapshot($init = false)
    {
        return $this->snapshots[$this->getSnapshotId($init)] ?? null;
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
