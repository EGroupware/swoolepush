<?php
/**
 * Pushserver for EGroupware based on PHPswoole
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-At-egroupware.org>
 * @package swoolpush
 * @copyright (c) 2024 by Ralf Becker <rb-At-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\SwoolePush;

use \Swoole\Websocket\Server as WebsocketServer;
use \Swoole\Table;

class PushServer extends WebsocketServer
{
	/**
	 * @var Table
	 */
	public $table;

	public function __construct(string $host='0.0.0.0', int $port=9501, int $max_users=1024)
	{
		parent::__construct($host, $port);

		$this->table = new Table($max_users);
		$this->table->column('session', Table::TYPE_STRING, 40);
		$this->table->column('user', Table::TYPE_STRING, 40);
		$this->table->column('instance', Table::TYPE_STRING, 40);
		$this->table->column('account_id', Table::TYPE_INT);
		$this->table->create();
	}
}