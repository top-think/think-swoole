<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\swoole;

use think\App;
use think\swoole\concerns\InteractsWithHttp;
use think\swoole\concerns\InteractsWithPools;
use think\swoole\concerns\InteractsWithRpcServer;
use think\swoole\concerns\InteractsWithRpcClient;
use think\swoole\concerns\InteractsWithServer;
use think\swoole\concerns\InteractsWithSwooleTable;
use think\swoole\concerns\InteractsWithWebsocket;
use think\swoole\concerns\WithApplication;

/**
 * Class Manager
 */
class Manager
{
    use InteractsWithServer,
        InteractsWithSwooleTable,
        InteractsWithHttp,
        InteractsWithWebsocket,
        InteractsWithPools,
        InteractsWithRpcClient,
        InteractsWithRpcServer,
        WithApplication;

    /**
     * @var App
     */
    protected $container;

    /** @var PidManager */
    protected $pidManager;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'workerError',
        'workerExit',
        'packet',
        'task',
        'finish',
        'pipeMessage',
        'managerStart',
        'managerStop',
        'request',
    ];

    /**
     * Manager constructor.
     * @param App        $container
     * @param PidManager $pidManager
     */
    public function __construct(App $container, PidManager $pidManager)
    {
        $this->container  = $container;
        $this->pidManager = $pidManager;
    }

    /**
     * Initialize.
     */
    protected function initialize(): void
    {
        $this->prepareTables();
        $this->preparePools();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
        $this->prepareRpcServer();
        $this->prepareRpcClient();
    }

}
