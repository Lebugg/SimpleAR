<?php

use \SimpleAR\Database\Builder\DeleteBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class DeleteBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testRootOption()
    {
        $builder = new DeleteBuilder();

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
