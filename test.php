<?php
/**
 * Testing Push Server for EGroupware using PHP Swoole extension
 *
 * To use on commandline:
 * docker exec -it egroupware bash
 * HTTP_HOST=example.org php /usr/share/egroupware/swoolepush/test.php
 *
 * Please note:
 * - backoff-time and failed-attempts are stored in APCu / shared memory and
 *   therefore are NOT the same for web-usage and command-line!
 * - for command-line the host need to be specified as shown above for the test to succeed!
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2020 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
use EGroupware\Api;
use EGroupware\Api\Json\Push;
use EGroupware\SwoolePush\Backend;

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => PHP_SAPI !== 'cli' ? 'admin' : 'login',
		'noheader' => true,
	]
];

require_once __DIR__.'/../header.inc.php';

if (PHP_SAPI !== 'cli')
{
	Api\Framework::message($message=lang('Checking PUSH (green for success or red for failure)'), 'error');
	echo $egw->framework->header();
	echo "<pre>\n";
	$success_start = "<span style='color: green; font-weight: bold'>";
	$failure_start = "<span style='color: red; font-weight: bold'>";
	$end = '</span>';
}
else
{
	echo "\n";
	$success_start = $failure_start = $end = '';
}

function check_push($ignore_cache=false)
{
	global $success_start, $failure_start, $end;
	$only_fallback = Push::onlyFallback($ignore_cache);
	$result = ($only_fallback ?
			$failure_start.lang('Using fallback via regular JSON requests') :
			$success_start.lang('Using native Swoole Push')).$end;
	echo "Push::onlyFallback()=".json_encode($only_fallback).' --> '.$result."\n\n";
	echo "SwoolPush\Backend::failedAttempts()=".Backend::failedAttempts().", SwoolePush\Backend::backoffTime=".Backend::backoffTime();
}

check_push();

if (Backend::failedAttempts() > Backend::MAX_FAILED_ATTEMPTS)
{
	if (empty($_POST['reset']) && PHP_SAPI !== 'cli')
	{
		echo " <form style='display:inline-block; margin:0' method='post'><input type='submit' name='reset' value='Reset' class='padding: 5px'/></form>\n";
	}
	else
	{
		echo "\nresetting to SwoolePush\Backend::failedAttempts()=".Backend::failedAttempts(-2*Backend::MAX_FAILED_ATTEMPTS).
			" and SwoolePush\Backend::backoffTime()=".Backend::backoffTime()."seconds\n";
	}
}
else echo "\n";
echo "\n";

echo "SwoolePush\Backend->online()=";
try {
	echo json_encode(array_map(function($account_id) {
			return Api\Accounts::id2name($account_id);
		},(new Backend())->online()))."\n\n";

	if (PHP_SAPI !== 'cli')
	{
		(new Backend())->addGeneric(Push::SESSION, 'message', [
			'message' => $message,
			'type'    => 'info',
		]);
	}
}
catch (Exception $e) {
	echo $failure_start.$e->getMessage().$end."\n";
}

check_push(true);

echo "\n\nPush->online()=".json_encode(array_map(function($account_id) {
	return Api\Accounts::id2name($account_id);
},(new Push())->online()))."\n\n";

if (PHP_SAPI !== 'cli')
{
	echo "\n<form style='display:inline-block; margin:0'><input type='submit' value='Retry' style='padding: 5px'/></form>\n";
}
