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
	 */
	public function exists();

	/**
	 * Open session readonly and return it's values
	 *
	 * @return array
	 * @throws \RuntimeException
	 */
	public function open();
}