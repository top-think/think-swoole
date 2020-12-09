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

use Swoole\Table as SwooleTable;

class Table
{
    public const TYPE_INT = 1;

    public const TYPE_STRING = 3;

    public const TYPE_FLOAT = 2;

    /**
     * Registered swoole tables.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Add a swoole table to existing tables.
     *
     * @param string $name
     * @param SwooleTable $table
     *
     * @return Table
     */
    public function add(string $name, SwooleTable $table)
    {
        $this->tables[$name] = $table;

        return $this;
    }

    /**
     * Get a swoole table by its name from existing tables.
     *
     * @param string $name
     *
     * @return SwooleTable $table
     */
    public function get(string $name)
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * Get all existing swoole tables.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->tables;
    }

    /**
     * Dynamically access table.
     *
     * @param string $key
     *
     * @return SwooleTable
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }
}
