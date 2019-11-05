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

namespace EGroupware\SwoolePush;

/**
 * Readonly non-blocking sessions for Swoole
 */
class Session
{
	protected $id;

	/**
	 * Constructor
	 *
	 * @param string $id
	 * @param string $path =null
	 * @param string $handler =null
	 * @throws RuntimeException
	 */
	function __construct($id, $path=null, $handler=null)
	{
		$this->id = $id;
		if (empty($path)) $path = ini_get('session.save_path');
		if (empty($handler)) $handler = ini_get('session.save_handler');

		//error_log(__METHOD__."('$id', '$path', '$handler')");
		switch($handler)
		{
			case 'files':
				$this->backend = new Session\Files($this->id, $path);
				break;

			case 'memcache':
			case 'memcached':
				$this->backend = new Session\Memcached($this->id, $path);
				break;

			default:
				throw new RuntimeException("Not implemented session.save_handler=$handler!");
		}
	}

	/**
	 * Check if given session exists
	 *
	 * @return bool
	 */
	function exists()
	{
		return $this->backend->exists();
	}

	/**
	 * Open session readonly and return it's values
	 *
	 * @return array
	 * @throws RuntimeException
	 */
	function open()
	{
		return $this->backend->open();
	}
}
