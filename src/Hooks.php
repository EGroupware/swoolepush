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
		if (basename($_SERVER['PHP_SELF']) !== 'login.php')
		{
			return [self::ws_url()];
		}
		return [];
	}

	/**
	 * Runs after framework js are loaded and includes all dependencies
	 *
	 * @param array $data
	 */
	public static function framework_header($data)
	{
		if ($data['popup'] === false && !empty($GLOBALS['egw']->session->sessionid))
		{
			$data['extra']['websocket-url'] = self::ws_url();
			$data['extra']['websocket-tokens'] = $GLOBALS['egw']->session->session_flags === 'A' ?
				[Tokens::session(), null, null] : Tokens::all();    // send anonymous session/user only a session token
			$data['extra']['websocket-account_id'] = $GLOBALS['egw_info']['user']['account_id'];
			$data['extra']['grants'] = $GLOBALS['egw']->acl->ajax_get_all_grants();
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
		// do NOT push a not explicitly set type, as it from an not yet push aware app
		if (empty($data['type']) || $data['type'] === 'unknown') return;

		// limit send data to ACL relevant and privacy save ones eg. just "owner"
		$extra = null;
		if (!empty($data['data']) && ($push_data = Api\Link::get_registry($data['app'], 'push_data')))
		{
			// apps (eg. calendar) can also specify a callback to clean the data
			if (!is_array($push_data) && is_callable($push_data))
			{
				$extra = $push_data($data['data']);
			}
			else
			{
				$extra = array_intersect_key($data['data'], array_flip((array)$push_data));
			}
		}
		//error_log(__METHOD__."(".json_encode($data).") push_data=".json_encode($push_data)." --> extra=".json_encode($extra));

		$push = new Api\Json\Push(Api\Json\Push::ALL);
		$push->apply("egw.push", [[
			'app'   => $data['app'],
            'id'    => $data['id'],
            'type'  => $data['type'],
            'acl'   => $extra,
            'account_id' => $GLOBALS['egw_info']['user']['account_id']
		]]);
	}
}