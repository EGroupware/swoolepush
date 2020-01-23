<?php
/**
 * Hooks for Swoole Push Server
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolpush
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush;

use EGroupware\Api;

class Hooks
{
	const APPNAME = 'swoolepush';

	/**
	 * Generate websocket url
	 */
	protected static function ws_url()
	{
		list($proto, $rest) = explode('://', Api\Framework::getUrl(Api\Framework::link('/push')));

		return ($proto === 'https' ? 'wss' : 'ws').'://'.$rest;
	}

	/**
	 * Add connect-src for our websocket
	 *
	 * @return array
	 */
	public static function csp_connect_src()
	{
		return [self::ws_url()];
	}

	/**
	 * Runs after framework js are loaded and includes all dependencies
	 *
	 * @param array $data
	 */
	public static function framework_header($data)
	{
		error_log(__METHOD__."(".json_encode($data).")");
		if ($data['popup'] === false && !empty($GLOBALS['egw']->session->sessionid))
		{
			$data['extra']['websocket-url'] = self::ws_url();
			$data['extra']['websocket-tokens'] = Tokens::all();
		}
	}

	/**
	 * Add our backend
	 *
	 * @return string class-name
	 */
	public static function push_backends()
	{
		return Backend::class;
	}

	/**
	 * Hook called by Api\Links::notify method of changes in entries of all apps
	 *
	 * @param array $data
	 */
	public static function notify_all(array $data)
	{
		error_log(__METHOD__."(".json_encode($data).")");
		// limit send data to ACL relevant and privacy save ones eg. just "owner"
		$extra = null;
		if (!empty($data['data']) && ($push_data = Api\Link::get_registry($data['target_app'], 'push_data')))
		{
			$extra = array_intersect_key($data['data'], array_flip((array)$push_data));

			if (!is_array($push_data)) $extra = $extra[$push_data];
		}

		// get app from possible "app-subtype" string
		list($app) = explode('-', $data['app']);

		$push = new Api\Json\Push(Api\Json\Push::ALL);
		$push->apply("app.$app.push", [
			$data['type'],
			$data['app'],
			$data['id'],
			$extra,
		]);
	}
}