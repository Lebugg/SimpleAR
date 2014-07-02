<?php

use \SimpleAR\Database\Query;
use \SimpleAR\Database\Builder\InsertBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class InsertTest extends PHPUnit_Framework_TestCase
{
    public function testSelectOptionMethod()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));
        $insert = new Query(new InsertBuilder, new BaseCompiler, $conn);

        $insert->root('Article')
            ->fields('blogId', 'title')
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
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));
        $conn->expects($this->once())->method('lastInsertId')->will($this->returnValue(12));

        $insert = new Query(null, null, $conn);

        $this->assertEquals(12, $insert->getConnection()->lastInsertId());
    }

}
