#!/usr/bin/env php
<?php
/**
 * Start with:
 *
 * docker run --rm -it -v $(pwd):/var/www -p9501:9501 phpswoole/swoole
 *
 * Send message:
 *
 * curl -i -H 'Content-Type: application/json' -X POST 'https://boulder.egroupware.org/egroupware/push' \
 *	-d '{"type":"message","data":{"message":"Hi ;)","type":"notice"},"token":"66f801ad060c80ae6272b07e13d6538bfc86b636"}'
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

$table = new Swoole\Table(1024);
$table->column('tokens', Swoole\Table::TYPE_STRING, 3*40+2);
$table->create();

$server = new Swoole\Websocket\Server("0.0.0.0", 9501);
$server->table = $table;

$server->on('open', function (Swoole\Websocket\Server $server, Swoole\Http\Request $request)
{
	//var_dump($request);
	$sessionid = $request->cookie['sessionid'];	// Api\Session::EGW_SESSION_NAME
    echo "server: handshake success with fd{$request->fd} sessionid=$sessionid\n";
});

$server->on('message', function (Swoole\Websocket\Server $server, $frame)
{
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

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
		echo "Pushed for $token to $send subscribers: $msg\n";
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
		echo "Invalid request: {$request->server['request_method']} $uri\n".$request->rawcontent()."\n";
	}
});


$server->on('close', function (Swoole\Websocket\Server $server, $fd)
{
	$server->table->del($fd);

    echo "client {$fd} closed\n";
});

$server->start();