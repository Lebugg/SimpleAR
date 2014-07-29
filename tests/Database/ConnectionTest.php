<?php

use \SimpleAR\Database\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    public function testLogQuery()
    {
        $conn = new Connection();

        $expected = [[
            'sql' => "SELECT * FROM a WHERE b = 'value'",
            'time' => '12,000,000.00'
        ]];

        $sql = "SELECT * FROM a WHERE b = ?";
        $conn->logQuery($sql, ['value'], 12000);

        $this->assertEquals($expected, $conn->queries());
    }
}
