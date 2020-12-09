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

namespace think\swoole\concerns;

use Swoole\Table as SwooleTable;
use think\App;
use think\Container;
use think\swoole\Table;

/**
 * Trait InteractsWithSwooleTable
 *
 * @property Container $container
 * @property App $app
 */
trait InteractsWithSwooleTable
{
    /**
     * @var Table
     */
    protected $currentTable;

    /**
     * Register customized swoole tables.
     */
    protected function prepareTables()
    {
        $this->currentTable = new Table();
        $this->registerTables();
        $this->onEvent('workerStart', function () {
            $this->app->instance(Table::class, $this->currentTable);
            foreach ($this->currentTable->getAll() as $name => $table) {
                $this->app->instance("swoole.table.{$name}", $table);
            }
        });
    }

    /**
     * Register user-defined swoole tables.
     */
    protected function registerTables()
    {
        $tables = $this->container->make('config')->get('swoole.tables', []);

        foreach ($tables as $key => $value) {
            $table   = new SwooleTable($value['size']);
            $columns = $value['columns'] ?? [];
            foreach ($columns as $column) {
                if (isset($column['size'])) {
                    $table->column($column['name'], $column['type'], $column['size']);
                } else {
                    $table->column($column['name'], $column['type']);
                }
            }
            $table->create();

            $this->currentTable->add($key, $table);
        }
    }
}
