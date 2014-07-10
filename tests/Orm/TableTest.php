<?php

use \SimpleAR\Orm\Table;

class TableTest extends PHPUnit_Framework_TestCase
{
    public function testIntegrity()
    {
        // Ok.
        $t = new Table('a', 'art_id', array('title'));
        $this->assertEquals(array('id'), $t->getPrimaryKey());
        $this->assertEquals(array('id' => 'art_id', 'title' => 'title'), $t->getColumns());

        // Ko.
        $t = new Table('a', array('id'), array('title'));
        $this->assertEquals(array('id' => 'id', 'title' => 'title'), $t->getColumns());
    }

    public function testGetPrimaryKey()
    {
        $expected = array('id');

        $t = new Table('articles', 'art_id', array());
        $this->assertEquals($expected, $t->getPrimaryKey());

        $t = new Table('articles', array('id'), array('id' => 'art_id'));
        $this->assertEquals($expected, $t->getPrimaryKey());

        $expected = array('a1', 'a2');

        $t = new Table('articles', array('a1', 'a2'), array('a1' => 'a_1', 'a2' => 'a_2'));
        $this->assertEquals($expected, $t->getPrimaryKey());
    }

    public function testGetColumns()
    {
        $expected = array(
            'blogId' => 'blog_id',
            'id' => 'art_id',
            'title' => 'title',
        );

        $t = new Table('articles', 'art_id', array('title', 'blogId' => 'blog_id'));
        $actual = $t->getColumns(); ksort($actual);
        $this->assertSame($expected, $actual);

        $t = new Table('articles', array('id'), array('id' => 'art_id', 'title', 'blogId' => 'blog_id'));
        $actual = $t->getColumns(); ksort($actual);
        $this->assertSame($expected, $actual);
    }

    public function testColumnRealName()
    {
        $t = new Table('articles', 'art_id', array('title', 'blogId' => 'blog_id'));

        $this->assertEquals('art_id', $t->columnRealName('id'));
        $this->assertEquals('blog_id', $t->columnRealName('blogId'));
        $this->assertEquals(array('title', 'blog_id'), $t->columnRealName(array('title', 'blogId')));
        $this->assertEquals(array('art_id', 'blog_id'), $t->columnRealName(array('id', 'blogId')));
    }
}
