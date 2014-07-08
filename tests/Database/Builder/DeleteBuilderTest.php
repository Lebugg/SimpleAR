<?php

use \SimpleAR\Database\Builder\DeleteBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class DeleteBuilderTest extends PHPUnit_Framework_TestCase
{
    private $_builder;
    private $_compiler;
    private $_conn;

    public function setUp()
    {
        global $sar;

        $this->_builder = new DeleteBuilder();
        $this->_compiler = new BaseCompiler();
        $this->_conn = $sar->db->connection();
    }

    public function testRootOption()
    {
        $builder = $this->_builder;
        //$query  = $this->getMock('\SimpleAR\Database\Query', array(), array($builder, $this->_compiler, $this->_conn));

        // Using model
        $options = array(
            'root' => 'Article',
        );

        $components = $builder->build($options);
        $this->assertEquals('articles', $components['deleteFrom']);


        // Using table name
        $options = array(
            'root' => 'articles',
        );

        $components = $builder->build($options);
        $this->assertEquals('articles', $components['deleteFrom']);
    }
}
