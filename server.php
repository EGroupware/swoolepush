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
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
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

$table = new Swoole\Table(1024);
$table->column('tokens', Swoole\Table::TYPE_STRING, 3*40+2);
$table->create();

$server = new Swoole\Websocket\Server("0.0.0.0", 9501);
$server->table = $table;

/**
 * Callback for successful Websocket handshake
 *
 * @todo move session check before handshake
 */
$server->on('open', function (Swoole\Websocket\Server $server, Swoole\Http\Request $request)
{
	//var_dump($request);
	$sessionid = $request->cookie['sessionid'];	// Api\Session::EGW_SESSION_NAME
	$session = new EGroupware\SwoolePush\Session($sessionid, 'memcached1:11211,memcached2:11211', 'memcached');
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
$server->on('message', function (Swoole\Websocket\Server $server, $frame)
{
    error_log("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");

	if (($data = json_decode($frame->data, true)))
	{
		if (isset($data['subscribe']) && count($data['subscribe']) === 3)
		{
			$server->table->set($frame->fd, ['tokens' => implode(':', $data['subscribe'])]);
			$server->push($frame->fd, json_encode([
				'type' => 'message',
				'data' => ['message' => 'Successful connected to push server :)']
			]));
		}
	}
});

/**
 * Callback for received HTTP request
 */
$server->on('request', function (Swoole\Http\Request $request, Swoole\Http\Response $response) use($server)
{
	$token = $request->get['token'] ?? null;

	switch ($request->server['request_method'])
	{
		case 'GET':
			$msg = $request->get['msg'];
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
	}
	if (!empty($token) && !empty($msg))
	{
		$send = 0;
		foreach($server->connections as $fd)
		{
			if ($server->exist($fd) && ($data = $server->table->get($fd, 'tokens')))
			{
				$tokens = explode(':', $data);

				if (is_string($token) && in_array($token, $tokens) ||
					is_array($token) && array_intersect($token, $tokens))
				{
					$server->push($fd, $msg);
					++$send;
				}
			}
		}
		error_log("Pushed for $token to $send subscribers: $msg");
	    $response->header("Content-Type", "text/pain; charset=utf-8");
	    $response->end("$send subscribers notified\n");
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
$server->on('close', function (Swoole\Websocket\Server $server, $fd)
{
	$server->table->del($fd);

    echo "client {$fd} closed\n";
});

$server->start();