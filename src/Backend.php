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

	/**
	 * After how many failed attempts, stop trying
	 */
	const MAX_FAILED_ATTEMPTS = 3;
	/**
	 * How long to stop trying after N failed attempts
	 */
	const MIN_BACKOFF_TIME = 60;
	const MAX_BACKOFF_TIME = 3600;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->url = Api\Framework::getUrl(Api\Framework::link('/push'));
		// stopping endless Travis logs because of http:///egroupware/ url not reachable
		if (substr($this->url, 0, 8) === 'http:///') self::$failed_attempts = true;

		if (($n=self::failedAttempts()) > self::MAX_FAILED_ATTEMPTS)
		{
			throw new Api\Exception\NotFound("Stopped trying to connect to push server $this->url after $n failed attempts!");
		}
	}

	/**
	 * Return or increment failed attempts
	 *
	 * If the failed attempts exceed MAX_FAILED_ATTEMPTS=3, we stop trying to talk to push server for
	 * a exponential increased (doubled) time between MIN_BACKOFF_TIME=60 and MAX_BACKOFF_TIME=3600.
	 *
	 * @param ?int $incr =null
	 * @return int number of failed attempts
	 */
	public static function failedAttempts($incr=null)
	{
		static $failed_attempts;

		if (!isset($failed_attempts))
		{
			$failed_attempts = (int)Api\Cache::getInstance(__CLASS__, 'failed-attempts');
		}
		if (isset($incr))
		{
			if (($failed_attempts += $incr) < 0) $failed_attempts = 0;

			Api\Cache::setInstance(__CLASS__, 'failed-attempts', $failed_attempts,
				self::backoffTime($failed_attempts > self::MAX_FAILED_ATTEMPTS ? true : ($failed_attempts ? null : false)));
		}
		return $failed_attempts;
	}

	/**
	 * Return current backoff time
	 *
	 * @param ?bool $double=null true: double the time up to MAX_BACKOFF_TIME, false: reset time to MIN_BACKOFF_TIME
	 * @return int backoff time
	 */
	public static function backoffTime($double=null)
	{
		static $backoff_time;
		if (!isset($backoff_time) && !($backoff_time = (int)Api\Cache::getInstance(__CLASS__, 'backoff-time')))
		{
			$backoff_time = self::MIN_BACKOFF_TIME;
		}
		if ($double === false && $backoff_time > self::MIN_BACKOFF_TIME)
		{
			Api\Cache::setInstance(__CLASS__, 'backoff-time', $backoff_time = self::MIN_BACKOFF_TIME);
			error_log(__METHOD__."($incr) reset backoff-time to $backoff_time seconds");
		}
		elseif ($double)
		{
			if (($backoff_time *= 2) > self::MAX_BACKOFF_TIME) $backoff_time = self::MAX_BACKOFF_TIME;
			error_log(__METHOD__."($incr) increased backoff-time to $backoff_time seconds");
			Api\Cache::setInstance(__CLASS__, 'backoff-time', $backoff_time);
		}
		return $backoff_time;
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param ?int $account_id account_id to push message too
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
			self::failedAttempts(-1);
			return $body;
		}
		// not try again for 1h
		self::failedAttempts(1);

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
			self::failedAttempts(-1);
			return json_decode($data, true);
		}
		// not try again for 1h
		self::failedAttempts(1);

		// send it now via the fallback method
		$fallback = new notifications_push();
		return $fallback->online();
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
