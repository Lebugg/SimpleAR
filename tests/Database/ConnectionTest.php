<?php

use \SimpleAR\Database\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase
{
    public function testLogQuery()
    {
        $conn = new Connection();

        $expected = [[
            'sql' => "SELECT * FROM a WHERE b = 'value'",
            'time' => 120 // Time in milliseconds.
        ]];

        $sql = "SELECT * FROM a WHERE b = ?";
        $conn->logQuery($sql, ['value'], 0.12); // 0.12: time in seconds.

        $this->assertEquals($expected, $conn->queries());
    }
}
