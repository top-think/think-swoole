<?php

namespace think\swoole;

use Swoole\Process;

class PidManager
{
    /** @var string */
    protected $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function getPid()
    {
        if (is_readable($this->file)) {
            return (int) file_get_contents($this->file);
        }

        return 0;
    }

    /**
     * 是否运行中
     * @return bool
     */
    public function isRunning()
    {
        $pid = $this->getPid();

        return $pid > 0 && Process::kill($pid, 0);
    }

    /**
     * Kill process.
     *
     * @param int $sig
     * @param int $wait
     *
     * @return bool
     */
    public function killProcess($sig, $wait = 0)
    {
        $pid = $this->getPid();
        $pid > 0 && Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning()) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning();
    }

}
