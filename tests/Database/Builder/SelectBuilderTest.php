<?php

use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class SelectBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testAggregate()
    {
        $b = new SelectBuilder();
        $b->aggregate('COUNT', '*');
        $components = $b->build();

        $expected = array(
            array(
                'columns' => array('*'),
                'function' => 'COUNT',
                'tableAlias' => '',
                'resultAlias' => '',
            ),
        );
        $this->assertEquals($expected, $components['aggregates']);

        $b = new SelectBuilder();
        $b->root('Blog');
        $b->aggregate('COUNT', 'articles/id', '#articles');
        $b->aggregate('AVG', 'articles/author/age', 'mediumAge');
        $components = $b->build();

        $expected = array(
            array(
                'columns' => array('id'),
                'function' => 'COUNT',
                'tableAlias' => 'articles',
                'resultAlias' => '#articles',
            ),
            array(
                'columns' => array('age'),
                'function' => 'AVG',
                'tableAlias' => 'articles.author',
                'resultAlias' => 'mediumAge',
            ),
        );
        $this->assertEquals($expected, $components['aggregates']);
    }

    public function testCount()
    {
        $b = $this->getMock('SimpleAR\Database\Builder\SelectBuilder', array('aggregate'));

        $b->expects($this->exactly(3))->method('aggregate')->withConsecutive(
            array('COUNT', '*', ''),
            array('COUNT', 'attribute'),
            array('COUNT', 'my.attribute', 'coolAlias')
        );

        $b->count();
        $b->count('attribute');
        $b->count('my.attribute', 'coolAlias');
    }

    public function testLimit()
    {
        $b = new SelectBuilder();
        $b->limit(5);

        $components = $b->build();

        $this->assertEquals(5, $components['limit']);
    }

    public function testOffset()
    {
        $b = new SelectBuilder();
        $b->offset(12);

        $components = $b->build();

        $this->assertEquals(12, $components['offset']);
    }

    public function testOrderBy()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->orderBy('author/lastName');
        $b->orderBy('created_at', 'DESC');

        $components = $b->build();

        $expected = array(
            array(
                'tableAlias' => 'author',
                'column' => 'last_name',
                'sort' => 'ASC',
            ),
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
                'sort' => 'DESC',
            ),
        );


        $this->assertEquals($expected, $components['orderBy']);
    }
}
