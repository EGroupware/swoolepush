<?php
/**
 * Copyright 2009-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category   Horde
 * @copyright  2009-2016 Horde LLC
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Stream_Wrapper
 * @subpackage UnitTests
 */

/**
 * Tests for the Combine wrapper.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2009-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Stream_Wrapper
 * @subpackage UnitTests
 */
class Horde_Stream_Wrapper_CombineTest extends PHPUnit_Framework_TestCase
{
    public function testUsage()
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, '12345');

        $data = array('ABCDE', $fp, 'fghij');

        $stream = Horde_Stream_Wrapper_Combine::getStream($data);

        $this->assertEquals('ABCDE12345fghij', fread($stream, 1024));
        $this->assertEquals(true, feof($stream));
        $this->assertEquals(0, fseek($stream, 0));
        $this->assertEquals(-1, fseek($stream, 0));
        $this->assertEquals(0, ftell($stream));
        $this->assertEquals(0, fseek($stream, 5, SEEK_CUR));
        $this->assertEquals(5, ftell($stream));
        $this->assertEquals(10, fwrite($stream, '0000000000'));
        $this->assertEquals(0, fseek($stream, 0, SEEK_END));
        $this->assertEquals(20, ftell($stream));
        $this->assertEquals(false, feof($stream));

        fclose($stream);
    }
}
