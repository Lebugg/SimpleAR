<?php

use \SimpleAR\Database\Builder\UpdateBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class UpdateBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSet()
    {
        $b = new UpdateBuilder();
        $b->root('Article')->set('title', 'Title')->set('blogId', 12);
        $components = $b->build();

        $expected = array(
            array(
                'tableAlias' => '_',
                'column' => 'title',
                'value' => 'Title',
            ),
            array(
                'tableAlias' => '_',
                'column' => 'blog_id',
                'value' => '12',
            ),
        );

        $this->assertEquals($expected, $components['set']);

        $b = new UpdateBuilder();
        $b->root('Article')->set(array('title' => 'Title', 'blogId' => 12));
        $components = $b->build();

        $this->assertEquals($expected, $components['set']);
    }
}
