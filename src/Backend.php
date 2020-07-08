<?php
/**
 * Hooks for Swoole Push Server
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush;

use EGroupware\Api;
use notifications_push;

/**
 * Class to push json requests to client-side
 */
class Backend extends Credentials implements Api\Json\PushBackend
{
	protected $connection;
	protected $url;

	protected static $use_fallback;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->url = Api\Framework::getUrl(Api\Framework::link('/push'));
		// stopping endless Travis logs because of http:///egroupware/ url not reachable
		if (substr($this->url, 0, 8) === 'http:///') self::$use_fallback = true;

		if (!isset(self::$use_fallback))
		{
			self::$use_fallback = Api\Cache::getInstance(__CLASS__, 'use-fallback');
		}

		if (self::$use_fallback === true)
		{
			throw new Api\Exception\NotFound("Can't connect to push server $this->url!");
		}
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param int $account_id account_id to push message too
	 * @param string $key
	 * @param mixed $data
	 * @return string|false response from push server "N subscribers notified"
	 *	or false if push server could not be reached or did not return 2xx HTTP status
	 */
	public function addGeneric($account_id, $key, $data)
	{
		if (!isset($account_id))
		{
			$token = Tokens::session();
		}
		elseif ($account_id === 0)
		{
			$token = Tokens::instance();
		}
		else
		{
			$token = Tokens::User($account_id);
		}
		//error_log(__METHOD__."($account_id, '$key', ".json_encode($data).") pushing to token $token");
		$header = [];
		if (($sock = self::http_open($this->url.'?token='.urlencode($token), 'POST', json_encode([
				'type' => $key,
				'data' => $data,
			]), [
				'Content-Type' => 'application/json'
			])) &&
			($response = stream_get_contents($sock)) &&
			($body = self::parse_http_response($response, $header)) &&
            substr($header[0], 9, 3)[0] == 2)
		{
			return $body;
		}
		// not try again for 1h
		Api\Cache::setInstance(__CLASS__, 'use-fallback', self::$use_fallback=true, 3600);

		// send it now via the fallback method
		$fallback = new notifications_push();
		$fallback->addGeneric($account_id, $key, $data);

		return false;
	}

	/**
	 * Get users online / connected to push-server
	 *
	 * @return array of integer account_id currently available for push
	 */
	public function online()
	{
		if (($sock = self::http_open($this->url.'?token='.urlencode(Tokens::instance()))) &&
			($response = stream_get_contents($sock)) &&
			($data = self::parse_http_response($response, $header)) &&
			substr($header[0], 9, 3)[0] == 2)
		{
			return json_decode($data, true);
		}
		return [];
	}

	/**
	 * Open connection for HTTP request
	 *
	 * @param string|array $url string with url or already passed like return from parse_url
	 * @param string $method ='GET'
	 * @param string $body =''
	 * @param array $header =array() additional header like array('Authentication' => 'basic xxxx')
	 * @param resource $context =null to set eg. ssl context like ca
	 * @param float $timeout =.2 0 for async connection
	 * @return resource|boolean socket still in blocking mode
	 */
	protected static function http_open($url, $method='GET', $body='', array $header=[], $context=null, $timeout=.2)
	{
		$parts = is_array($url) ? $url : parse_url($url);
		$addr = ($parts['scheme'] == 'https'?'ssl://':'tcp://').$parts['host'].':';
		$addr .= isset($parts['port']) ? (int)$parts['port'] : ($parts['scheme'] == 'https' ? 443 : 80);
		if (!isset($context)) $context = stream_context_create ();
		$errno = $errstr = null;
		if (!($sock = stream_socket_client($addr, $errno, $errstr, $timeout,
			$timeout ? STREAM_CLIENT_CONNECT : STREAM_CLIENT_ASYNC_CONNECTC, $context)))
		{
			error_log(__METHOD__."('$url', ...) stream_socket_client('$addr', ...) $errstr ($errno)");
			return false;
		}
		$request = $method.' '.$parts['path'].(empty($parts['query'])?'':'?'.$parts['query'])." HTTP/1.1\r\n".
			"Host: ".$parts['host'].(empty($parts['port'])?'':':'.$parts['port'])."\r\n".
			"User-Agent: swoolepush/src/Backend.php\r\n".
			"Authorization: Bearer ".self::getBearerToken()."\r\n".
			"Accept: application/json\r\n".
			"Cache-Control: no-cache\r\n".
			"Pragma:no-cache\r\n".
			"Connection: close\r\n";

		// Content-Length header is required for methods containing a body
		if (in_array($method, array('PUT','POST','PATCH')))
		{
			$header['Content-Length'] = strlen($body);
		}
		foreach($header as $name => $value)
		{
			$request .= $name.': '.$value."\r\n";
		}
		$request .= "\r\n";
		//if ($method != 'GET') error_log($request.$body);

		if (fwrite($sock, $request.$body) === false)
		{
			error_log(__METHOD__."('$url', ...) error sending request!");
			fclose($sock);
			return false;
		}
		return $sock;
	}

	/**
	 * Parse body from HTTP response and dechunk it if necessary
	 *
	 * @param string $response
	 * @param array& $headers =null headers on return, lowercased name => value pairs
	 * @return string body of response
	 */
	protected static function parse_http_response($response, array &$headers=null)
	{
		list($header, $body) = explode("\r\n\r\n", $response, 2);
		$headers = array();
		foreach(explode("\r\n", $header) as $line)
		{
			$parts = preg_split('/:\s*/', $line, 2);
			if (count($parts) == 2)
			{
				$headers[strtolower($parts[0])] = $parts[1];
			}
			else
			{
				$headers[] = $parts[0];
			}
		}
		// dechunk body if necessary
		if (isset($headers['transfer-encoding']) && $headers['transfer-encoding'] == 'chunked')
		{
			$chunked = $body;
			$body = '';
			while($chunked && (list($size, $chunked) = explode("\r\n", $chunked, 2)) && $size)
			{
				$body .= substr($chunked, 0, hexdec($size));
				if (true) $chunked = substr($chunked, hexdec($size)+2);	// +2 for "\r\n" behind chunk
			}
		}
		return $body;
	}
}
