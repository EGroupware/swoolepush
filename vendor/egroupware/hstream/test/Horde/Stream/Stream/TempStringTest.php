<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Stream
 * @subpackage UnitTests
 */

/**
 * Tests for the Horde_Stream_TempString class, with the data being stored
 * in a native PHP string variable internally.
 *
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Stream
 * @subpackage UnitTests
 */
class Horde_Stream_Stream_TempStringTest extends Horde_Stream_Stream_TestBase
{
    protected function _getOb()
    {
        return new Horde_Stream_TempString();
    }

    public function testNotUsingStream()
    {
        $ob = $this->_getOb();
        $ob->add('123');

        $this->assertFalse($ob->use_stream);
    }

    public function testMaxMemory()
    {
        $ob = new Horde_Stream_TempString(array('max_memory' => 1));
        $ob->add('abcdefg');
        $this->assertTrue($ob->use_stream);
        $this->assertEquals('abcdefg', $ob->__toString());

        $ob = new Horde_Stream_TempString(array('max_memory' => 10));
        $ob->add('abcd');
        $this->assertFalse($ob->use_stream);
        $this->assertEquals('abcd', $ob->__toString());
    }

}
