<?php
/**
 * Credentials for Swoole Push Server
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2020 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush;

/**
 * Credentials for Swoole Push Server
 *
 * The token is read from (or automatically generate in) config.inc.php in swoolpush directory usually
 * symlinked to /var/lib/egroupware-push/config.inc.php with /var/lib/egroupware-push being a volume
 * shared between egroupware and push-server containers.
 */
class Credentials
{
	private static $bearer_token;

	public static function getBearerToken()
	{
		if (empty(self::$bearer_token))
		{
			$bearer_token = null;
			$file = dirname(__DIR__).'/config.inc.php';
			// try a couple times before giving up
			for ($n=0; $n < 10 && !file_exists($file); ++$n)
			{
				if (!file_put_contents($file, "<?php\n\$bearer_token = '".base64_encode(random_bytes(16))."';\n", LOCK_EX))
				{
					usleep(100);
					clearstatcache(false, $file);
				}
			}
			if (!(include $file) || empty($bearer_token))
			{
				die("Could not read or generate PUSH server token file $file!");
			}
			self::$bearer_token = $bearer_token;
		}
		return self::$bearer_token;
	}
}
