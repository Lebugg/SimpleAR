<?php

use \SimpleAR\Database\Builder\UpdateBuilder;
use \SimpleAR\Database\JoinClause;

class UpdateBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSet()
    {
        $b = new UpdateBuilder();
        $b->root('Article')->set('title', 'Title')->set('blogId', 12);
        $components = $b->build();

        $set = [
            ['tableAlias' => '_', 'column' => 'title', 'value' => 'Title'],
            ['tableAlias' => '_', 'column' => 'blog_id', 'value' => '12'],
        ];
        $updateFrom = [new JoinClause('articles', '_')];

        $this->assertEquals($set, $components['set']);
        $this->assertEquals($updateFrom, $components['updateFrom']);

        $b = new UpdateBuilder();
        $b->root('Article')->set(['title' => 'Title', 'blogId' => 12]);
        $components = $b->build();

        $this->assertEquals($set, $components['set']);
        $this->assertEquals($updateFrom, $components['updateFrom']);
    }
}
