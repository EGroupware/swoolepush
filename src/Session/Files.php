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
class Files implements Backend
{
	protected $id;
	protected $path;

	/**
	 * Constructor
	 *
	 * @param string $id
	 * @param string $path =null
	 * @throws \RuntimeException
	 */
	function __construct($id, $path=null)
	{
		$this->id = $id;
		$this->path = $path;
	}

	/**
	 * Check if given session exists
	 *
	 * @return bool
	 */
	public function exists()
	{
		return file_exists($this->filename());
	}

	/**
	 * Open session readonly and return its values
	 *
	 * @param bool $try_reconnect
	 * @return array
	 * @throws \RuntimeException if session is not found
	 * @throws \Exception on failed connection AFTER reconnect
	 */
	public function open(bool $try_reconnect=false)
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			if (!session_start())
			{
				throw new \Exception('session_start() failed');
			}
		}
		$_SESSION = [];	// session_decode does NOT clear it

		if (!$this->exists() || !session_decode(file_get_contents($this->filename())))
		{
			throw new \RuntimeException("Could not open session $this->id!");
		}
		return $_SESSION;
	}

	/**
	 * Filename of session file
	 *
	 * @return bool
	 */
	protected function filename()
	{
		return $this->path.'/sess_'.$this->id;
	}
}