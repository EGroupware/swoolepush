# memcache-pool

## 安装

> composer require easyswoole/memcache-pool

## 注册

`EasySwooleEvent.php`的`initialize`事件：

```php
$config = new \EasySwoole\Memcache\Config();
$config->setHost('127.0.0.1');
$config->setPort(11211);

\EasySwoole\MemcachePool\MemcachePool::getInstance()->register($config,'default');
```

> 池的名字默认为`default`

## 获取池

```php
/** @var \EasySwoole\MemcachePool\Pool $pool */
$pool = \EasySwoole\MemcachePool\MemcachePool::getInstance()->getPool('default');
```

需要自己回收池内对象.

`pool`具体用法请看[连接池组件](https://github.com/easy-swoole/pool).

建议使用以下方案进行缓存`defer、invoke`;


## Defer

```php
/** @var \EasySwoole\Memcache\Memcache $client */
$client = \EasySwoole\MemcachePool\MemcachePool::defer('default');
$client->set('testKey','1');
$client->get('testKey');
```

连接池不存在,将会返回`null`.

协程结束后将会自动归还此链接到连接池当中.


## Invoke

```php
$ret = \EasySwoole\MemcachePool\MemcachePool::invoke(function (\EasySwoole\Memcache\Memcache $memcache){
    return $memcache->get('testKey');
},'default');
```

连接池不存在,将会返回`null`.
成功会返回回调函数的结果.

回调函数执行完,会自动归还此链接到连接池当中.
