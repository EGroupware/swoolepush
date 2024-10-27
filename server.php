#!/usr/bin/env php
<?php
/**
 * Push Server for EGroupware using PHP Swoole extension
 *
 * Start with:
 *
 * docker run --rm -it -v $(pwd):/var/www -v /var/lib/php/sessions:/var/lib/php/sessions \
 *	--add-host memcached1:192.168.65.2 -p9501:9501 phpswoole/swoole
 *
 * Send message (you can get a token from the server output, when a client connects):
 *
 * curl -i -H 'Content-Type: application/json' -X POST 'https://boulder.egroupware.org/egroupware/push?token=<token>' \
 *	-d '{"type":"message","data":{"message":"Hi ;)","type":"notice"}}'
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2019-24 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling server.php as web-page
{
	die('<h1>server.php must NOT be called as web-page --> exiting !!!</h1>');
}
if (!class_exists('Swoole\\Websocket\\Server'))
{
	echo phpinfo();
	die("\n\nPHP extension swoole not loaded!\n");
}

// this is necessary to use session_decode(), BEFORE there is any output
ini_set('session.save_path', '/var/lib/php/sessions');
if (session_status() !== PHP_SESSION_ACTIVE)
{
	session_start();
}
require __DIR__.'/vendor/autoload.php';

// allow to set a higher number of push users
if (($max_users = (int)($_SERVER['EGW_MAX_PUSH_USERS'] ?? 1024)) < 1024)
{
    $max_users = 1024;
}
$max_users_used = 0;

$server = new EGroupware\SwoolePush\PushServer("0.0.0.0", 9501, $max_users);
$table = $server->table;

// read Bearer Token from Backend class
$bearer_token = EGroupware\SwoolePush\Credentials::getBearerToken();
$valid_authorization = [
	'Bearer '.$bearer_token,
	// Dovecot 2.2+ OX push-plugin only supports basic auth
	'Basic '.base64_encode('Bearer:'.$bearer_token),
];

/**
 * Callback for successful Websocket handshake
 *
 * @todo move session check before handshake
 */
$server->on('open', function (Swoole\Websocket\Server $server, Swoole\Http\Request $request)
{
	//var_dump($request);
	$sessionid = $request->cookie['sessionid'];	// Api\Session::EGW_SESSION_NAME
	$session = new EGroupware\SwoolePush\Session($sessionid); //, 'memcached1:11211,memcached2:11211', 'memcached');
	if (!$session->exists())
	{
		error_log("server: handshake success with fd{$request->fd}, FAILED with unknown sessionid=$sessionid");
		$server->close($request->fd);
	}
	else
	{
		error_log("server: handshake success with fd{$request->fd} existing sessionid=$sessionid");
	}
});

/**
 * Callback for received Websocket message
 */
$server->on('message', function (Swoole\Websocket\Server $server, Swoole\WebSocket\Frame $frame) use ($max_users, &$max_users_used)
{
	error_log("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}, users current/highest/max: ".$server->table->count()."/$max_users_used/$max_users");
	
	// client testing the websocket connection with a "ping" message --> send back "pong"
	if ($frame->data === 'ping')
	{
		$server->push($frame->fd, 'pong');
	}
	// client subscribes to channels
	elseif (($data = json_decode($frame->data, true)))
	{
		if (isset($data['subscribe']) && count($data['subscribe']) === 3)
		{
			$server->table->set($frame->fd, [
				'session' => $data['subscribe'][0],
				'user'    => $data['subscribe'][1],
				'instance' => $data['subscribe'][2],
				'account_id' => $data['account_id'],
			]);
			if (($used = $server->table->count()) > $max_users_used)
            {
                $max_users_used = $used;
            }
		}
	}
});

/**
 * Callback for received HTTP request
 */
$server->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use($server, $valid_authorization)
{
	$token = $request->get['token'] ?? null;

	// check Bearer token
	if (!in_array($request->header['authorization'], $valid_authorization))
	{
		$response->status(401);
		$response->header('WWW-Authenticate', 'Bearer realm="EGroupware Push Server"');
		$response->header('WWW-Authenticate', 'Basic realm="EGroupware Push Server"');
		$response->end((!isset($request->header['authorization']) ? 'Missing' : 'Wrong').' Bearer Token!');
		return;
	}

	switch ($request->server['request_method'])
	{
		case 'GET':
			$msg = $request->get['msg'] ?? null;
			break;
		case 'POST':
			$msg = $request->rawcontent();
			if (empty($token))
			{
				$data = json_decode($msg, true);
				$token = $data['token'];
				unset($data['token']);
				$msg = json_encode($data);
			}
			break;
		case 'PUT':
			// Dovecot 2.2+ OX or Dovecot 2.3+ Lua push plugin
			if (strpos($request->header['content-type'], 'application/json') === 0)
			{
				if (($data = json_decode($request->rawcontent(), true)) === null)
				{
					$response->status(400);
					$response->end('Invalid JSON: ' . json_last_error_msg());
					error_log('Invalid JSON: ' . json_last_error_msg());
					return;
				}
				$total = 0;
				foreach (explode(';;', $data['user']) as $user)
				{
					$matches = null;
					if (!preg_match('/^(\d+::\d+);([^@]+)@(.*)$/', $user, $matches))
					{
						$response->status(400);
						$response->write('Can NOT parse user attribute!');
						error_log('Can NOT parse user attribute!');
						continue;
					}
					list(, $account_acc_id, $token, $host) = $matches;
					// decode mime-encoded from or subject eg. =?utf-8?b?something=?=
					foreach(['from', 'subject'] as $name)
					{
						if (!empty($data[$name]) && strpos($data[$name], '=?') !== false)
						{
							require_once __DIR__.'/vendor/autoload.php';
							$data[$name] = Horde_Mime::decode($data[$name]);
						}
					}
					$uids = array_map(function($uid) use ($account_acc_id, $data)
					{
						return $account_acc_id . '::' . base64_encode($data['folder']) . '::' . $uid;
					}, (array)$data['imap-uid']);
					$data['event'] = ucfirst($data['event']);   // Dovecot 2.2 uses "messageNew", while 2.3 "MessageNew" :(
					$msg = json_encode([
						'type' => 'apply',
						'data' => [
							'func' => 'egw.push',
							'parms' => [[
								'app' => 'mail',
								'id' => count($uids) == 1 ? $uids[0] : $uids,
								'type' => in_array($data['event'], ['MessageNew', 'MessageAppend']) ? 'add' :
									($data['event'] === 'MessageExpunge' ? 'delete' : 'update'),
								'acl' => $data,
								'account_id' => 0,
							]]
						],
					]);
					$send = 0;
					foreach ($server->connections as $fd)
					{
						if ($server->exist($fd) && ($tokens = $server->table->get($fd)))
						{
							if (is_array($token) && in_array($tokens['user'], $token) ||
                                is_string($token) && ($token === $tokens['user'] || $token === $tokens['session'] || $token === $tokens['instance']))
							{
								$server->push($fd, $msg);
								++$send;
								++$total;
							}
						}
					}
                    if (is_array($token)) $token = count($token).' tokens';
					error_log("Pushed for $token to $send subscribers: $msg");
				}
				$response->header("Content-Type", "text/pain; charset=utf-8");
				$response->end("$total subscribers notified\n");
				return;
			}
	}
	/*error_log($request->server['request_method'].' '.$request->server['request_uri'].
		(!empty($request->server['query_string'])?'?'.$request->server['query_string']:'').' '.$request->server['server_protocol'].
		' from remote_addr '.$request->server['remote_addr'].', X-Forwarded-For: '.$request->header['x-forwarded-for'].' Host: '.$request->header['host']);*/
	if (!empty($token) && !empty($msg))
	{
		$send = 0;
		foreach($server->connections as $fd)
		{
			if ($server->exist($fd) && ($tokens = $server->table->get($fd)))
			{
				if (is_array($token) && in_array($tokens['user'], $token) ||
					is_string($token) && ($token === $tokens['user'] || $token === $tokens['session'] || $token === $tokens['instance']))
				{
					$server->push($fd, $msg);
					++$send;
				}
			}
		}
		if (is_array($token)) $token = count($token).' tokens';
		error_log("Pushed for $token to $send subscribers: $msg");
		$response->header("Content-Type", "text/pain; charset=utf-8");
		$response->end("$send subscribers notified\n");
	}
	elseif (!empty($token))
	{
		$account_ids = [];
		foreach($server->connections as $fd)
		{
			if ($server->exist($fd) && ($data = $server->table->get($fd)))
			{
				if ($token === $data['instance'])
				{
					$account_ids[] = $data['account_id'];
				}
			}
		}
		if ($account_ids) $account_ids = array_unique($account_ids);
		$count = count($account_ids);
		error_log("Returned for instance-token $token $count unique account_id's");
		$response->header("Content-Type", "application/json");
		$response->end(json_encode($account_ids));
	}
	else
	{
		// not calling $response->end() return 500 to client
		$uri = $request->server['request_uri'];
		if (!empty($request->server['query_string']))
		{
			$uri .= '?'.$request->server['query_string'];
		}
		error_log("Invalid request: {$request->server['request_method']} $uri\n".$request->rawcontent());
	}
});

/**
 * Callback for closed connection
 */
$server->on('close', function (Swoole\Websocket\Server $server, int $fd)
{
	$server->table->del($fd);
});

$server->start();