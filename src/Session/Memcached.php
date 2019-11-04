<?php
/**
 * Readonly non-blocking sessions for Swoole
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolpush
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush\Session;

/**
 * Readonly non-blocking sessions for Swoole
 */
class Memcached implements Backend
{
	protected $id;
	/**
	 * @var \EasySwoole\Memcache\Memcache[]
	 */
	protected static $memcached;

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
			foreach(explode(',', $path ?? ini_get('session.save_path')) as $host_port)
			{
				list ($host, $port) = explode(':', $host_port);
				$config = new \EasySwoole\Memcache\Config([
					'host' => $host,
					'port' => $port ?? 11211,
				]);
				self::$memcached[$host_port] = new \EasySwoole\Memcache\Memcache($config);
			}
		}
	}

	/**
	 * Check if given session exists
	 *
	 * @return bool
	 */
	public function exists()
	{
		try {
			return $this->open() !== null;
		}
		catch(\RuntimeException $e) {
			echo $e->getMessage();
			return false;
		}
	}

	/**
	 * Open session readonly and return it's values
	 *
	 * @return array
	 * @throws \RuntimeException
	 */
	public function open()
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			session_start();
		}
		$_SESSION = [];	// session_decode does NOT clear it

		$key = $this->key();
		foreach(self::$memcached as $memcached)
		{
			try {
				$data = $memcached->get($key);
				//var_dump("memcached('$key'=", $data);
				if ($data !== null) break;
			}
			catch (\Exception $e) {
				echo $e->getMessage()."\n";
				continue;
			}
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
		return (ini_get('memcached.sess_prefix') ?: 'memc.sess.').$this->id;
	}
}
