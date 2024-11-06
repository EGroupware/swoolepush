<?php

namespace EasySwoole\MemcachePool\Tests;

use EasySwoole\Memcache\Config;
use EasySwoole\Memcache\Memcache;
use EasySwoole\MemcachePool\Exception\PoolException;
use EasySwoole\MemcachePool\MemcachePool;
use EasySwoole\MemcachePool\Pool;
use EasySwoole\MemcachePool\PoolConfig;
use PHPUnit\Framework\TestCase;

class MemcachePoolTest extends TestCase
{
    static $config;

    static function setUpBeforeClass(): void
    {
        self::$config = new Config([
            'host' => HOST
        ]);
    }

    public function testRegister()
    {
        $poolConfig = MemcachePool::getInstance()->register(self::$config);
        $this->assertInstanceOf(PoolConfig::class, $poolConfig);

        $this->expectException(PoolException::class);
        $this->expectExceptionMessage('memcache pool[default] is already been register');
        MemcachePool::getInstance()->register(self::$config);
    }

    public function testGetPool()
    {
        $memcachePool = MemcachePool::getInstance()->getPool();
        $this->assertInstanceOf(Pool::class, $memcachePool);

        $memcachePool = MemcachePool::getInstance()->getPool('none');
        $this->assertNull($memcachePool);
    }

    public function testDefer()
    {
        $memcache = MemcachePool::defer('none');
        $this->assertNull($memcache);

        $memcache = MemcachePool::defer();
        $this->assertInstanceOf(Memcache::class, $memcache);

        $this->assertTrue($memcache->set('testKey', 'memcache'));
        $this->assertEquals('memcache', $memcache->get('testKey'));
    }

    public function testInvoke()
    {
        $memcache = MemcachePool::invoke(function () {
        }, 'none');
        $this->assertNull($memcache);

        $ret = MemcachePool::invoke(function (Memcache $memcache) {
            $this->assertInstanceOf(Memcache::class, $memcache);

            $this->assertTrue($memcache->set('testKey', 'memcache'));
            $this->assertEquals('memcache', $memcache->get('testKey'));

            return 'success';
        });

        $this->assertEquals('success', $ret);

    }
}
