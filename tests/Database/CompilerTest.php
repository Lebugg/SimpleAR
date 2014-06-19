<?php

use \SimpleAR\Database\Compiler;

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
}
