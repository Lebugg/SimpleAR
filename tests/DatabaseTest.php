<?php

use \SimpleAR\Database;

class DatabaseTest extends PHPUnit_Framework_TestCase
{
    public function testConnection()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection');
        $db = new Database($conn);

        $this->assertEquals($conn, $db->getConnection());

        $db = new Database();
        $this->assertNull($db->getConnection());
        $db->setConnection($conn);
        $this->assertEquals($conn, $db->getConnection());
    }

    public function testGetCompilerJustCallsConnection()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('getCompiler'));
        $conn->expects($this->once())->method('getCompiler')->will($this->returnValue(12));

        $db = new Database($conn);

        $this->assertEquals(12, $db->getCompiler());
    }
}
