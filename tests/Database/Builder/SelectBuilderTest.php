<?php

use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Query;
use \SimpleAR\Facades\DB;


/**
 * @coversDefaultClass \SimpleAR\Database\Builder\SelectBuilder
 */
class SelectBuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers ::aggregate()
     */
    public function testAggregate()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'fetchAll'));
        $q = new Query(new SelectBuilder, $conn);

        $sql = 'SELECT `blog_id` AS `blogId` ,AVG(`views`) AS `views_avg` FROM `articles` GROUP BY `blog_id`';
        $expected = [
            ['blogId' => 1, 'views_avg' => 100],
            ['blogId' => 2, 'views_avg' => 123.45],
            ['blogId' => 3, 'views_avg' => 1200],
        ];
        $conn->expects($this->once())->method('query')->with($sql, []);
        $conn->expects($this->once())->method('fetchAll')->will($this->returnValue($expected));

        $res = $q->root('Article')
          ->groupBy('blogId')
          ->avg('views', 'views_avg');
        $this->assertEquals($expected, $res);

        // - - - Withour groupBy

        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'fetchAll'));
        $q = new Query(new SelectBuilder, $conn);

        $sql = 'SELECT COUNT(*) FROM `articles`';
        $conn->expects($this->once())->method('query')->with($sql, []);
        $conn->expects($this->once())->method('fetchAll')->will($this->returnValue([['COUNT(*)' => 12]]));

        $res = $q->root('Article')->count();
        $this->assertEquals(12, $res);
    }

    public function testAddAggregate()
    {
        $b = new SelectBuilder;
        $b->addAggregate('COUNT', '*');
        $components = $b->build();

        $expected = array(
            array(
                'cols' => array('*'),
                'fn' => 'COUNT',
                'tAlias' => '',
                'resAlias' => '',
            ),
        );
        $this->assertEquals($expected, $components['aggregates']);

        $b = new SelectBuilder;
        $b->root('Blog');
        $b->addAggregate('COUNT', 'articles/id', '#articles');
        $b->addAggregate('AVG', 'articles/author/age', 'mediumAge');
        $b->select(['*']);
        $components = $b->build();

        $aggs = array(
            array(
                'cols' => array('id'),
                'fn' => 'COUNT',
                'tAlias' => 'articles',
                'resAlias' => '#articles',
            ),
            array(
                'cols' => array('age'),
                'fn' => 'AVG',
                'tAlias' => 'articles.author',
                'resAlias' => 'mediumAge',
            ),
        );
        $cols = [
            '_' => ['columns' => array_flip(Blog::table()->getColumns()), 'resAlias' => ''],
        ];
        $this->assertEquals($aggs, $components['aggregates']);
        $this->assertEquals($cols, $components['columns']);
    }

    /**
     * @covers ::count()
     */
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

    /**
     * @covers ::limit()
     */
    public function testLimit()
    {
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->limit(5);

        $components = $b->build();

        $this->assertEquals(5, $components['limit']);
    }

    /**
     * @covers ::limit()
     */
    public function testLimitOffset()
    {
        $b = $this->getMock('SimpleAR\Database\Builder\SelectBuilder', array('offset'));
        $b->expects($this->once())->method('offset')->with(10);
        $b->root('Blog');
        $b->limit(5, 10);

        $components = $b->build();
        $this->assertEquals(5, $components['limit']);
    }

    /**
     * @covers ::offset()
     */
    public function testOffset()
    {
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->offset(12);

        $components = $b->build();

        $this->assertEquals(12, $components['offset']);
    }

    /**
     * @covers ::orderBy()
     */
    public function testOrderBy()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->orderBy('author/lastName');
        $b->orderBy('created_at', 'DESC');

        $components = $b->build();

        $expected = array(
            array(
                'tAlias' => 'author',
                'column' => 'last_name',
                'sort' => 'ASC',
            ),
            array(
                'tAlias' => '_',
                'column' => 'created_at',
                'sort' => 'DESC',
            ),
        );

        $this->assertEquals($expected, $components['orderBy']);

        $b = new SelectBuilder();
        $b->root('Article');
        $b->orderBy(['author/lastName', 'created_at' => 'DESC']);
        $components = $b->build();
        $this->assertEquals($expected, $components['orderBy']);
    }

    /**
     * @covers ::groupBy()
     */
    public function testGroupBy()
    {
        $b = new SelectBuilder();
        $b->root('Article');
        $b->groupBy('author/lastName');
        $b->groupBy('created_at');

        $components = $b->build();

        $expected = array(
            array(
                'tAlias' => 'author',
                'column' => 'last_name',
            ),
            array(
                'tAlias' => '_',
                'column' => 'created_at',
            ),
        );


        $this->assertEquals($expected, $components['groupBy']);

        // With array
        $b = new SelectBuilder();
        $b->root('Article');
        $b->groupBy(['author/lastName', 'created_at']);
        $components = $b->build();
        $this->assertEquals($expected, $components['groupBy']);
    }

    /**
     * @covers ::with()
     */
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
            '_' => ['columns' => Blog::table()->getColumns(), 'resAlias' => ''],
            'articles' => ['columns' => Article::table()->getColumns(), 'resAlias' => 'articles'],
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);

        // Deeper "with"
        $b = new SelectBuilder();
        $b->root('Blog');
        $b->with('articles/author')->select(['*']);

        $components = $b->build();

        $jc[] = (new JoinClause('authors', 'articles.author', JoinClause::LEFT))->on('articles', 'author_id', 'articles.author', 'id');
        $columns['articles.author'] = ['columns' => Author::table()->getColumns(), 'resAlias' => 'articles.author'];

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);
    }

    /**
     * @covers ::with()
     */
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
            '_' => ['columns' => Article::table()->getColumns(), 'resAlias' => ''],
            'blog' => ['columns' => Blog::table()->getColumns(), 'resAlias' => 'blog'],
            'author' => ['columns' => Author::table()->getColumns(), 'resAlias' => 'author'],
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
            '_' => ['columns' => Article::table()->getColumns(), 'resAlias' => ''],
            'readers' => ['columns' => User::table()->getColumns(), 'resAlias' => 'readers'],
        );

        $this->assertEquals($jc, $components['from']);
        $this->assertEquals($columns, $components['columns']);
    }

    /**
     * @covers ::select()
     */
    public function testSelect()
    {
        $b = new SelectBuilder;
        $b->root('Article');
        $b->select(['authorId', 'title', 'created_at']);
        $expr = new Expression('AVG(created_at) AS average');
        $b->select($expr);

        $components = $b->build();
        $columns = [
            '_' => ['columns' => [
                'id' => 'id',
                'authorId' => 'author_id',
                'title' => 'title',
                'created_at' => 'created_at',
            ], 'resAlias' => ''],
            ['column' => $expr, 'alias' => ''],
        ];
        $this->assertEquals($columns, $components['columns']);


        $b = new SelectBuilder;
        $b->root('User');
        $b->select(['firstName', 'lastName', 'name']);

        $components = $b->build();
        $columns = [
            '_' => ['columns' => ['id' => 'id', 'firstName' => 'firstName', 'lastName' => 'name', 'name' => 'name'], 'resAlias' => ''],
        ];
        $this->assertEquals($columns, $components['columns']);
    }

    public function testJoinSeveralManyManyInARow()
    {
        $b = new SelectBuilder;
        $b->root('Article');
        $b->where('readers/followers/id', 12);
        $b->select(['*'], false);

        $components = $b->build();
        $jc[] = (new JoinClause('articles', '_'));
        $jc[] = (new JoinClause('articles_USERS', 'readers_m'))->on('_', 'id', 'readers_m', 'article_id');
        $jc[] = (new JoinClause('USERS', 'readers'))->on('readers_m', 'user_id', 'readers', 'id');
        $jc[] = (new JoinClause('USERS_USERS', 'readers.followers_m'))->on('readers', 'id', 'readers.followers_m', 'user_id');
        $jc[] = (new JoinClause('USERS', 'readers.followers'))->on('readers.followers_m', 'user_id', 'readers.followers', 'id');

        $this->assertEquals($jc, $components['from']);
    }

    public function testDistinctObject()
    {
        $b = new SelectBuilder;
        $b->root('Article');
        $b->count(DB::distinct(Article::table()->getPrimaryKey()));
        $b->select(['*'], false);

        $components = $b->build();
        $c = new BaseCompiler;
        $sql = $c->compileSelect($components);

        $expected = 'SELECT * ,COUNT(DISTINCT `id`) FROM `articles`';
        $this->assertEquals($expected, $sql);
    }

    /**
     * @covers ::distinct()
     */
    public function testDistinct()
    {
        $expected = [
            'columns' => [
                '_' => ['columns' => Article::table()->getColumns(), 'resAlias' => ''],
            ],
            'from' => [
                new JoinClause('articles', '_'),
            ],
            'distinct' => true,
        ];

        $b = new SelectBuilder;
        $b->root('Article');
        $b->distinct();
        $this->assertEquals($expected, $b->build());

        $b = new SelectBuilder;
        $b->root('Article');
        $b->distinct('*');
        $this->assertEquals($expected, $b->build());

        $b = new SelectBuilder;
        $b->root('Article');
        $b->distinct('*', false);
        $expected['columns'] = ['_' => ['columns' => ['*'], 'resAlias' => '']];
        $this->assertEquals($expected, $b->build());

        $b = new SelectBuilder;
        $b->root('Article');
        $b->distinct(['blogId', 'authorId']);
        $expected['columns'] = ['_' => ['columns' => ['blogId' => 'blog_id', 'authorId' => 'author_id', 'id' => 'id'], 'resAlias' => '']];
        $this->assertEquals($expected, $b->build());
    }

    public function testJoin()
    {
        $b = new SelectBuilder;
        $b->root('Blog')
            ->join('articles')
            ->select(['*'], false);
        $components = $b->build();
        $jc = array(
            (new JoinClause('blogs', '_')),
            (new JoinClause('articles', 'articles', JoinClause::INNER))->on('_', 'id', 'articles', 'blog_id')
        );
        $this->assertEquals($jc, $components['from']);
    }

}
