<?php
/**
 * EGroupware SwoolePush - setup
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolepush
 * @copyright (c) 2019 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */


$setup_info['swoolepush']['name']    = 'swoolepush';
$setup_info['swoolepush']['title']   = 'Swoole Push Server';
$setup_info['swoolepush']['version'] = '19.1';
$setup_info['swoolepush']['app_order'] = 7;
$setup_info['swoolepush']['enable']  = 2;
//$setup_info['swoolepush']['autoinstall'] = true;	// install automatically on update
$setup_info['swoolepush']['author'] = 'Ralf Becker';
$setup_info['swoolepush']['maintainer'] = array(
	'name'  => 'EGroupware GmbH',
	'url'   => 'https://www.egroupware.org',
);
$setup_info['swoolepush']['license']  = 'GPLv2+';
$setup_info['swoolepush']['description'] = 'Push server for EGroupware based on Swoole PHP extension';

/* The hooks this app includes, needed for hooks registration */
$setup_info['swoolepush']['hooks']['framework_header'] = 'EGroupware\SwoolePush\Hooks::framework_header';
$setup_info['swoolepush']['hooks']['csp-connect-src'] = 'EGroupware\SwoolePush\Hooks::csp_connect_src';
$setup_info['swoolepush']['hooks']['push-backends'] = 'EGroupware\SwoolePush\Hooks::push_backends';

/* Dependencies for this app to work */
$setup_info['swoolepush']['depends'][] = array(
	'appname' => 'api',
	'versions' => array('19.1')
);