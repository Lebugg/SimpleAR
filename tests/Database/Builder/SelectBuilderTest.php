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
        $b->select(['*']);
        $components = $b->build();

        $aggs = array(
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
        $cols = [
            '_' => ['columns' => array_flip(Blog::table()->getColumns()), 'resultAlias' => ''],
        ];
        $this->assertEquals($aggs, $components['aggregates']);
        $this->assertEquals($cols, $components['columns']);
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
        $b->select(array('*'));

        $components = $b->build();

        $jc = array(
            (new JoinClause('blogs', '_')),
            (new JoinClause('articles', 'articles', JoinClause::LEFT))->on('_', 'id', 'articles', 'blog_id')
        );

        $columns = array(
            '_' => ['columns' => array_flip(Blog::table()->getColumns()), 'resultAlias' => ''],
            'articles' => ['columns' => array_flip(Article::table()->getColumns()), 'resultAlias' => 'articles'],
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);

        // Deeper "with"
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->with('articles/author')->select(['*']);

        $components = $b->build();

        $jc[] = (new JoinClause('authors', 'articles.author', JoinClause::LEFT))->on('articles', 'author_id', 'articles.author', 'id');
        $columns['articles.author'] = ['columns' => array_flip(Author::table()->getColumns()), 'resultAlias' => 'articles.author'];

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);
    }

    public function testWithOptionArray()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->with(['blog', 'author'])->select(['*']);

        $components = $b->build();

        $jc = array(
            (new JoinClause('articles', '_')),
            (new JoinClause('blogs', 'blog', JoinClause::LEFT))->on('_', 'blog_id', 'blog', 'id'),
            (new JoinClause('authors', 'author', JoinClause::LEFT))->on('_', 'author_id', 'author', 'id')
        );

        $columns = array(
            '_' => ['columns' => array_flip(Article::table()->getColumns()), 'resultAlias' => ''],
            'blog' => ['columns' => array_flip(Blog::table()->getColumns()), 'resultAlias' => 'blog'],
            'author' => ['columns' => array_flip(Author::table()->getColumns()), 'resultAlias' => 'author'],
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);

    }

    public function testJoinManyMany()
    {
        $b = new SelectBuilder;
        $b->root('Article');
        $b->with('readers')->select(['*']);

        $components = $b->build();

        $jc = array(
            (new JoinClause('articles', '_')),
            // "_m" stands for "middle".
            (new JoinClause('articles_USERS', 'readers_m', JoinClause::LEFT))->on('_', 'id', 'readers_m', 'article_id'),
            (new JoinClause('USERS', 'readers', JoinClause::LEFT))->on('readers_m', 'user_id', 'readers', 'id')
        );

        $columns = array(
            '_' => ['columns' => array_flip(Article::table()->getColumns()), 'resultAlias' => ''],
            'readers' => ['columns' => array_flip(User::table()->getColumns()), 'resultAlias' => 'readers'],
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);
    }

    public function testSelect()
    {
        $b = new SelectBuilder;
        $b->root('Article');
        $b->select(['authorId', 'title', 'created_at']);

        $components = $b->build();
        $columns = array(
            '_' => ['columns' => ['id', 'author_id', 'title', 'created_at'], 'resultAlias' => ''],
        );

        $this->assertEquals($columns, $components['columns']);
    }
}