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
interface Backend
{
	/**
	 * Constructor
	 *
	 * @param string $id
	 * @param string $path =null
	 * @throws \RuntimeException
	 */
	function __construct($id, $path=null);

	/**
	 * Check if given session exists
	 *
	 * @return bool
	 * @throws \Exception on failed connection AFTER reconnect, or session_start() returned false
	 */
	public function exists();

	/**
	 * Open session readonly and return its values
	 *
	 * @param bool $try_reconnect
	 * @return array
	 * @throws \RuntimeException if session is not found
	 * @throws \Exception on failed connection AFTER reconnect, or session_start() returned false
	 */
	public function open(bool $try_reconnect=true);
}