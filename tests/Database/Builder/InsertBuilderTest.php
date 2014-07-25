<?php

use \SimpleAR\Database\Builder\InsertBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;

class InsertBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testBuildWithoutUsingModel()
    {
        $builder = new InsertBuilder();

        $options = array(
            'fields' => array(
                'blog_id',
                'title',
            ),
            'values' => array(
                12,
                'Awesome!',
            ),
        );

        $components = $builder->build($options);

        $this->assertEquals($options['fields'], $components['insertColumns']);
        $this->assertEquals($options['values'], $components['values']);
    }

    public function testBuildUsingModel()
    {
        $builder = new InsertBuilder();

        $table = Article::table();
        $builder->setRootModel('Article');

        $options = array(
            'fields' => array(
                'blogId',
                'title',
            ),
            'values' => array(
                12,
                'Awesome!',
            ),
        );

        $components = $builder->build($options);

        $expectedFields = $table->columnRealName($options['fields']);
        $this->assertEquals($expectedFields, $components['insertColumns']);
        $this->assertEquals($options['values'], $components['values']);
    }
}
