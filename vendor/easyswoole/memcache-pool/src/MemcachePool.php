<?php

namespace EasySwoole\MemcachePool;

use EasySwoole\Component\Container;
use EasySwoole\Component\Singleton;
use EasySwoole\Memcache\Config;
use EasySwoole\Memcache\Memcache;
use EasySwoole\Pool\Config as PoolConfig;
use EasySwoole\MemcachePool\Exception\PoolException;

class MemcachePool
{
    use Singleton;

    /** @var Container */
    protected $container;


    public function __construct()
    {
        $this->container = new Container();
    }

    public function register(Config $config, string $name = 'default'): PoolConfig
    {
        if ($this->container->get($name) instanceof Pool) {
            throw new PoolException(
                sprintf("memcache pool[%s] is already been register", $name)
            );
        }

        $pool = new Pool($config);
        $this->container->set($name, $pool);
        return $pool->getConfig();
    }

    public function getPool(string $name = 'default'): ?Pool
    {
        return $this->container->get($name);
    }

    public static function defer(string $name = 'default', ?float $timeout = null): ?Memcache
    {
        $pool = static::getInstance()->getPool($name);

        if (!$pool) {
            return null;
        }

        return $pool->defer($timeout);
    }

    public static function invoke(callable $call, string $name = 'default', ?float $timeout = null)
    {
        $pool = static::getInstance()->getPool($name);

        if (!$pool) {
            return null;
        }

        return $pool->invoke($call, $timeout);
    }

}
