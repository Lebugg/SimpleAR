<?php

use \SimpleAR\Database\Query\Insert;

class InsertTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));
        $sar->db->setConnection($conn);
    }

    public function testSelectOptionMethod()
    {
        $insert = new Insert('Article');
        $insert->fields('blogId', 'title')
            ->values(12, 'Awesome!')
            ->run();

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?)';
        $expectedVal = array(12, 'Awesome!');

        $this->assertEquals($expectedSql, $insert->getSql());
        $this->assertEquals($expectedVal, $insert->getValues());
    }

    public function testInsertId()
    {
        global $sar;
        $conn = $sar->db->connection();
        $conn->expects($this->once())->method('lastInsertId')->will($this->returnValue(12));

        $insert = new Insert('Article');

        $this->assertEquals(12, $insert->insertId());
    }

}
