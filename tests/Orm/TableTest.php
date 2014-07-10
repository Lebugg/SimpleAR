<?php

use \SimpleAR\Orm\Table;

class TableTest extends PHPUnit_Framework_TestCase
{
    public function testGetColumns()
    {
        $t = new Table('articles', 'art_id', array('title', 'blogId' => 'blog_id'));

        $expected = array(
            'id' => 'art_id',
            'title' => 'title',
            'blogId' => 'blog_id',
        );
        $actual = $t->getColumns();
        ksort($expected);ksort($actual);
        $this->assertSame($expected, $actual);
    }
}
