<?php

namespace think\swoole;

use RuntimeException;
use think\App;
use think\Config;
use think\Container;
use think\Event;
use think\Http;
use think\Request;
use think\Response;
use think\swoole\contract\ResetterInterface;
use think\swoole\coroutine\Context;
use think\swoole\resetters\ClearInstances;
use think\swoole\resetters\ResetConfig;
use think\swoole\resetters\ResetEvent;

class Sandbox
{
    /**
     * The app containers in different coroutine environment.
     *
     * @var array
     */
    protected $snapshots = [];

    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    protected $resetters = [];

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
        $this->setInitialEvent();
        $this->setInitialResetters();

        return $this;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function run(Request $request): Response
    {
        $level = ob_get_level();
        ob_start();

        $response = $this->handleRequest($request);

        $content = $response->getContent();

        if (ob_get_level() == 0) {
            ob_start();
        }

        $this->getHttp()->end($response);

        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return $response;
    }

    protected function handleRequest(Request $request): Response
    {
        return $this->getHttp()->run($request);
    }

    public function init($fd = null)
    {
        if (!is_null($fd)) {
            Context::setData('_fd', $fd);
        }
        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

    /**
     * @return Http
     */
    protected function getHttp()
    {
        return $this->getApplication()->make(Http::class);
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

    public function clear($snapshot = true)
    {
        if ($snapshot) {
            unset($this->snapshots[$this->getSnapshotId()]);
        }
        Context::clear();
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
    public function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

}
