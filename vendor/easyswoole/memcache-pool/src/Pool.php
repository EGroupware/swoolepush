<?php


namespace EasySwoole\MemcachePool;

use EasySwoole\Memcache\Config as MemcacheConfig;
use EasySwoole\Memcache\Memcache;
use EasySwoole\Pool\MagicPool;

class Pool extends MagicPool
{
    public function __construct(MemcacheConfig $config)
    {
        parent::__construct(function () use ($config) {

            $memcache = new Memcache($config);
            $memcache->connect();

            return $memcache;
        }, new PoolConfig());
    }

    /**
     * @param Memcache $memcache
     * @return bool
     */
    public function itemIntervalCheck($memcache): bool
    {
        /** @var PoolConfig $config */
        $config = $this->getConfig();
        $autoPing = $config->getAutoPing();
        if ($autoPing > 0 && (time() - $memcache->__lastUseTime > $autoPing)) {
            try {
                $memcache->stats();
                $memcache->__lastUseTime = time();
                return true;
            } catch (\Throwable $throwable) {
                return false;
            }
        }

        return true;
    }

}