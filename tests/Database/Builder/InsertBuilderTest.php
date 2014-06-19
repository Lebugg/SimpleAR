<?php

use \SimpleAR\Database\Builder\InsertBuilder;

class InsertBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testBuildWithoutUsingModel()
    {
        $query  = $this->getMockForAbstractClass('\SimpleAR\Database\Query');
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

        $builder->build($query, $options);

        $this->assertEquals($options['fields'], $query->columns);
        $this->assertEquals($options['values'], $query->values);
    }

    public function testBuildUsingModel()
    {
        $query  = $this->getMockForAbstractClass('\SimpleAR\Database\Query');
        $builder = new InsertBuilder();

        $table = Article::table();
        $builder->setTable($table);

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

        $builder->build($query, $options);

        $expectedFields = $table->columnRealName($options['fields']);
        $this->assertEquals($expectedFields, $query->columns);
        $this->assertEquals($options['values'], $query->values);
    }
}
