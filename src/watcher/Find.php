<?php

namespace think\swoole\watcher;

use ArrayIterator;
use InvalidArgumentException;
use SplFileInfo;
use Swoole\Coroutine\System;
use Swoole\Timer;
use Symfony\Component\Finder\Iterator\ExcludeDirectoryFilterIterator;
use Symfony\Component\Finder\Iterator\FilenameFilterIterator;
use think\helper\Str;

class Find implements Driver
{
    protected $name;
    protected $directory;
    protected $exclude;

    public function __construct($directory, $exclude, $name)
    {
        $ret = System::exec('which find');
        if (empty($ret['output'])) {
            throw new InvalidArgumentException('find not exists.');
        }
        $ret = System::exec('find --help', true);
        if (Str::contains($ret['output'] ?? '', 'BusyBox')) {
            throw new InvalidArgumentException('find version not support.');
        }

        $this->directory = $directory;
        $this->exclude   = $exclude;
        $this->name      = $name;
    }

    public function watch(callable $callback)
    {
        $ms      = 2000;
        $seconds = ceil(($ms + 1000) / 1000);
        $minutes = sprintf('-%.2f', $seconds / 60);

        $dest = implode(' ', $this->directory);

        Timer::tick($ms, function () use ($callback, $minutes, $dest) {
            $ret = System::exec('find ' . $dest . ' -mmin ' . $minutes . ' -type f -print');
            if ($ret['code'] === 0 && strlen($ret['output'])) {
                $stdout = trim($ret['output']);

                $files = explode(PHP_EOL, $stdout);

                $iterator = new ArrayIterator();
                foreach ($files as $file) {
                    $file = new SplFileInfo($file);

                    $iterator[$file->getPathname()] = $file;
                }
                $iterator = new ExcludeDirectoryFilterIterator($iterator, $this->exclude);
                $iterator = new FilenameFilterIterator($iterator, $this->name, []);

                if (iterator_count($iterator) > 0) {
                    call_user_func($callback);
                }
            }
        });
    }

}
