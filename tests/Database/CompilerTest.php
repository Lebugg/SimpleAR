<?php

use \SimpleAR\Database\Compiler;
use \SimpleAR\Database\Expression;

class CompilerTest extends PHPUnit_Framework_TestCase
{
    public function testWrap()
    {
        $c = $this->getMockForAbstractClass('SimpleAR\Database\Compiler');
        $this->assertEquals('`hello`', $c->wrap('hello'));
    }

    public function testWrapArrayToString()
    {
        $c = $this->getMockForAbstractClass('SimpleAR\Database\Compiler');

        $toWrap = array('hello', 'world!', 'How are you?');
        $expected = '`hello`,`world!`,`How are you?`';

        $this->assertEquals($expected, $c->wrapArrayToString($toWrap));
    }

    public function testParameterize()
    {
        $c = $this->getMockForAbstractClass('SimpleAR\Database\Compiler');

        $this->assertEquals('?', $c->parameterize(12));
        $this->assertEquals('?', $c->parameterize('String'));

        $values = array('String', 12, 'Other@"\'&é"çà_ç_``');
        $this->assertEquals('(?,?,?)', $c->parameterize($values));

        $expr = new Expression("FN('My String')");
        $this->assertEquals("FN('My String')", $c->parameterize($expr));


        $values = array('String', 12, new Expression('DATE()'));
        $this->assertEquals('(?,?,DATE())', $c->parameterize($values));
    }

    public function testParameterizeWithModels()
    {
        $c = $this->getMockForAbstractClass('SimpleAR\Database\Compiler');

        $m1 = new Article;
        $m1->id = 2;
        $m2 = $this->getMock('Article', ['id']);
        $m2->method('id')->will($this->returnValue([4, 5]));

        $values = array('Yo', $m1, 12, $m2, 9);
        $this->assertEquals('(?,(?),?,(?,?),?)', $c->parameterize($values));
    }

    public function testColumnize()
    {
        $c = $this->getMockForAbstractClass('SimpleAR\Database\Compiler');

        $this->assertEquals('`author_id`', $c->columnize(['author_id']));
        $this->assertEquals('`a`,`b`,`c`', $c->columnize(['a', 'b', 'c']));
        $this->assertEquals('`t1`.`col1`', $c->columnize(['col1'], 't1'));
        $this->assertEquals('`t1`.`col1`,`t2`.`col2`', $c->columnize(['col1', 'col2'], ['t1', 't2']));
    }

}
