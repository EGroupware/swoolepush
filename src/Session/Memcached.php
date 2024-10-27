<?php
/**
 * Readonly non-blocking sessions for Swoole
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolpush
 * @copyright (c) 2019-24 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush\Session;

use EasySwoole\Memcache\Config;
use EasySwoole\MemcachePool\MemcachePool;
use EasySwoole\Memcache\Memcache;

/**
 * Readonly non-blocking sessions for Swoole
 */
class Memcached implements Backend
{
	protected $id;
	/**
	 * @var Config[]
	 */
	protected static $memcached;
	protected static $save_path;

	/**
	 * Constructor
	 *
	 * @param string $id
	 * @param string $path =null "server1[:11211][,server2[:11211]]"
	 * @throws \RuntimeException
	 */
	function __construct($id, $path=null)
	{
		$this->id = $id;

		if (!isset(self::$memcached))
		{
			foreach(explode(',', self::$save_path = $path ?? ini_get('session.save_path')) as $host_port)
			{
				list ($host, $port) = explode(':', $host_port);
				$config = new Config([
					'host' => $host,
					'port' => $port ?? 11211,
				]);
				self::$memcached[$host_port] = MemcachePool::getInstance()->register($config, $host_port);
			}
		}
	}

	/**
	 * Check if given session exists
	 *
	 * @return bool
	 * @throws \Exception on failed connection AFTER reconnect
	 */
	public function exists()
	{
		try {
			return $this->open() !== null;
		}
		catch(\RuntimeException $e) {
			error_log(__METHOD__."() ".$e->getMessage());
			return false;
		}
	}

	/**
	 * Open session readonly and return its values
	 *
	 * @param bool $try_reconnect
	 * @return array
	 * @throws \RuntimeException if session is not found
	 * @throws \Exception on failed connection AFTER reconnect
	 */
	public function open(bool $try_reconnect=true)
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			session_start();
		}
		$_SESSION = [];	// session_decode does NOT clear it

		$key = $this->key();
		$exceptions = [];
		foreach(self::$memcached as $host_port => $memcached)
		{
			try {
				$data = MemcachePool::invoke(static function (Memcache $memcache) use ($key) {
					return $memcache->get($key);
				}, $host_port);
				//var_dump("memcached->get('$key')=", $data);
				if ($data !== null) break;
			}
			catch (\Exception $e) {
				$exceptions[$host_port] = $e;
				error_log(__METHOD__."('$key', $try_reconnect) ".$e->getMessage());
				continue;
			}
		}
		// if all memcached gave an exception (not finding the session/key does NOT!)
		if (isset($e) && count($exceptions) === count(self::$memcached))
		{
			if ($try_reconnect)
			{
				error_log(__METHOD__."('$key', $try_reconnect) trying to reconnect now");
				self::$memcached = null;
				self::__construct($this->id, self::$save_path);
				return $this->open(false);
			}
			// throw our original (connection-failed) exception
			throw $e;
		}
		if (!$data || !session_decode($data))
		{
			throw new \RuntimeException("Could not open session $this->id!");
		}
		return $_SESSION;
	}

	/**
	 * Get the key used for the session
	 */
	protected function key()
	{
		return (ini_get('memcached.sess_prefix') ?: 'memc.sess.key.').$this->id;
	}
}