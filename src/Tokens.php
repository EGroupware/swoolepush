<?php
/**
 * Tokens for Swoole Push Server
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush;

use EGroupware\Api;

/**
 * Class to generate push tokens
 */
class Tokens
{
	const APPNAME = 'swoolepush';

	/**
	 * Get all tokens for the current session and user
	 *
	 * @return array
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function all()
	{
		$tokens = [];
		$tokens[] = self::session();
		$tokens[] = self::user();
		$tokens[] = self::instance();

		return $tokens;
	}

	/**
	 * Session token
	 *
	 * As client-side javascript has for good reason no access to sessionid cookie,
	 * we use a sha1 hash of it instead.
	 *
	 * @return string
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function session()
	{
		if(empty($GLOBALS['egw']->session->sessionid))
		{
			throw new Api\Exception\AssertionFailed("Can NOT generate session tokent without a sessionid!");
		}
		return sha1($GLOBALS['egw']->session->sessionid);
	}

	/**
	 * User token used by all sessions of a user
	 *
	 * @param int $account_id =null default, current user
	 * @return string
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function user($account_id=null)
	{
		if (empty($account_id))
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		if(empty($account_id))
		{
			throw new Api\Exception\AssertionFailed("Can NOT generate user tokent without an account_id!");
		}
		return self::hash($account_id);
	}

	/**
	 * User token used by all sessions of a user
	 *
	 * @return string
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function instance()
	{
		if(empty($GLOBALS['egw_info']['server']['install_id']))
		{
			throw new Api\Exception\AssertionFailed("Can NOT generate user tokent without an install_id!");
		}
		return self::hash($GLOBALS['egw_info']['server']['install_id']);
	}

	/**
	 * Use a secret to generate an unguessable token from a given value
	 *
	 * @param string|int $value
	 * @return string
	 */
	protected static function hash($value)
	{
		$config = Api\Config::read(self::APPNAME);

		if (empty($config['secret-date']) || $config['secret-date'] !== date('Y-m-d'))
		{
			Api\Config::save_value('secret-date', $config['secret-date'] = date('Y-m-d'), self::APPNAME);
			Api\Config::save_value('secret', $config['secret'] = Api\Auth::randomstring(16), self::APPNAME);
		}
		return sha1($config['secret'].$value);
	}

	/**
	 * Runs after framework js are loaded and includes all dependencies
	 *
	 * @param array $data
	 */
	public static function framework_header($data)
	{
		if(!$data['popup'] && !empty($GLOBALS['egw']->session->id))
		{
			$data['extra']['websocket-url'] = self::ws_url;
			$data['extra']['websocket-tokens'] = Tokens::get();
		}
	}
}