<?php

use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\JoinClause;

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
        $b->root('Blog');
        $b->limit(5);

        $components = $b->build();

        $this->assertEquals(5, $components['limit']);
    }

    public function testOffset()
    {
        $b = new SelectBuilder();
        $b->root('Blog');
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

    public function testGroupBy()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->groupBy('author/lastName');
        $b->groupBy('created_at');

        $components = $b->build();

        $expected = array(
            array(
                'tableAlias' => 'author',
                'column' => 'last_name',
            ),
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
            ),
        );


        $this->assertEquals($expected, $components['groupBy']);
    }

    public function testWithOption()
    {
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->with('articles');

        $components = $b->build();

        $jc = array(
            (new JoinClause('blogs', '_')),
            (new JoinClause('articles', 'articles', JoinClause::LEFT))->on('_', 'id', 'articles', 'blog_id')
        );

        $columns = array(
            '_' => array('columns' => array_flip(Blog::table()->getColumns())),
            'articles' => array('columns' => array_flip(Article::table()->getColumns())),
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);

        // Deeper "with"
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->with('articles/author');

        $components = $b->build();

        $jc[] = (new JoinClause('authors', 'articles.author', JoinClause::LEFT))->on('articles', 'author_id', 'articles.author', 'id');
        $columns['articles.author'] = array('columns' => array_flip(Author::table()->getColumns()));

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);
    }

    public function testWithOptionArray()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->with(['blog', 'author']);

        $components = $b->build();

        $jc = array(
            (new JoinClause('articles', '_')),
            (new JoinClause('blogs', 'blog', JoinClause::LEFT))->on('_', 'blog_id', 'blog', 'id'),
            (new JoinClause('authors', 'author', JoinClause::LEFT))->on('_', 'author_id', 'author', 'id')
        );

        $columns = array(
            '_' => array('columns' => array_flip(Article::table()->getColumns())),
            'blog' => array('columns' => array_flip(Blog::table()->getColumns())),
            'author' => array('columns' => array_flip(Author::table()->getColumns())),
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);

    }
}
