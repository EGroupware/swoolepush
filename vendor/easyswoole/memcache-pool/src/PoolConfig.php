<?php


namespace EasySwoole\MemcachePool;


use EasySwoole\Pool\Config;

class PoolConfig extends Config
{
    protected $autoPing = 5;

    /**
     * @return int
     */
    public function getAutoPing(): int
    {
        return $this->autoPing;
    }

    /**
     * @param int $autoPing
     */
    public function setAutoPing(int $autoPing): void
    {
        $this->autoPing = $autoPing;
    }
}