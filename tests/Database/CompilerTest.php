<?php

use \SimpleAR\Database\Compiler;
use \SimpleAR\Database\Expression;

class CompilerTest extends PHPUnit_Framework_TestCase
{
    public $compiler;

    public function setUp()
    {
        $this->compiler = $this->getMockForAbstractClass('\SimpleAR\Database\Compiler');
    }

    public function testWrap()
    {
        $compiler = $this->compiler;

        $this->assertEquals('`hello`', $compiler->wrap('hello'));
    }

    public function testWrapArrayToString()
    {
        $compiler = $this->compiler;

        $toWrap = array('hello', 'world!', 'How are you?');
        $expected = '`hello`,`world!`,`How are you?`';

        $this->assertEquals($expected, $compiler->wrapArrayToString($toWrap));
    }

    public function testParameterize()
    {
        $compiler = $this->compiler;

        $this->assertEquals('?', $compiler->parameterize(12));
        $this->assertEquals('?', $compiler->parameterize('String'));

        $values = array('String', 12, 'Other@"\'&é"çà_ç_``');
        $this->assertEquals('(?,?,?)', $compiler->parameterize($values));

        $expr = new Expression("FN('My String')");
        $this->assertEquals("FN('My String')", $compiler->parameterize($expr));


        $values = array('String', 12, new Expression('DATE()'));
        $this->assertEquals('(?,?,DATE())', $compiler->parameterize($values));
    }

    public function testColumnize()
    {
        $c = $this->compiler;

        $this->assertEquals('`author_id`', $c->columnize(array('author_id')));
        $this->assertEquals('`a`,`b`,`c`', $c->columnize(array('a', 'b', 'c')));
        $this->assertEquals('`t1`.`col1`', $c->columnize(array('col1'), 't1'));
        $this->assertEquals('`t1`.`col1`,`t1`.`col2`', $c->columnize(array('col1', 'col2'), 't1'));
        $this->assertEquals('`t1`.`col1` AS `_.attr1`', $c->columnize(array('col1' => 'attr1'), 't1', '_'));
        $this->assertEquals('`t1`.`col1` AS `_.attr1`,`t1`.`col2` AS `_.attr2`', $c->columnize(array('col1' => 'attr1', 'col2' => 'attr2'), 't1', '_'));
    }
}
