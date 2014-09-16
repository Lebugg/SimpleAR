<?php

use \Mockery as m;

use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Builder\InsertBuilder;
use \SimpleAR\Database\Builder\DeleteBuilder;
use \SimpleAR\Database\Builder\UpdateBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Orm\Builder as QueryBuilder;

/**
 * @coversDefaultClass \SimpleAR\Orm\Builder
 */
class BuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * This function tests the three call forms of QueryBuilder's constructor:
     *
     * *    (<root>);
     * *    (<root>, <root alias>);
     * *    (<root>, <root alias>, <Relation>);
     *
     * @covers ::__construct
     */
    public function testConstructor()
    {
        $qb = new QueryBuilder('Blog');
        $qb->getQueryOrNewSelect();
        $b = $qb->getQuery()->getBuilder();
        $this->assertEquals('Blog', $b->getRootModel());
        $this->assertEquals('_', $b->getRootAlias());

        // - - -

        $qb = new QueryBuilder('Article', 'a');
        $qb->getQueryOrNewSelect();
        $b = $qb->getQuery()->getBuilder();
        $this->assertEquals('Article', $b->getRootModel());
        $this->assertEquals('a', $b->getRootAlias());

        // - - -

        // $qb = new QueryBuilder('Author', 'articles.author', Article::relation('author'));
        // $qb->getQueryOrNewSelect();
        // $b = $qb->getQuery()->getBuilder();
        // $this->assertEquals('Author', $b->getRootModel());
        // $this->assertEquals('articles.author', $b->getRootAlias());
        // $jc = (new JoinClause('author', 'articles.author'))->on('articles', 'author_id', 'articles.author', 'id');
        // $this->assertEquals($jc, $b->getJoinClause('articles'));
    }

    /**
      Check that QueryBuilder uses objects stored in Database class.
     *
     */
    public function testDatabaseGetter()
    {
        global $sar;
        $qb = new QueryBuilder();

        $this->assertEquals($sar->db->getConnection(), $qb->getConnection());
    }

    public function testInsert()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', ['initQuery']);
        $qb->expects($this->once())->method('initQuery')
            ->with(new InsertBuilder, null, null, false, true);
        $qb->insert();
    }

    public function testInsertInto()
    {
        $sql = 'INSERT INTO `articles` (`title`,`author_id`) VALUES(?,?)';
        $val = ['Stuff', 12];
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query']);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->insert('articles')->fields(['title', 'author_id'])->values(['Stuff', 12])->run();
    }

    public function testDeleteWhere()
    {
        $sql = 'DELETE FROM `articles` WHERE `id` = ?';
        $val = [12];
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query']);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);
        $qb->delete('Article')->where('id', 12)->run();
    }

    public function testDeleteSetRootIfRootHasBeenCalled()
    {
        $qb = new QueryBuilder('Article');
        $query = $qb->delete();

        $this->assertEquals('Article', $query->getBuilder()->getRootModel());
    }

    public function testUpdate()
    {
        $sql = 'UPDATE `articles` SET `title` = ? WHERE `title` = ? AND `author_id` IN (?,?)';
        $val = ['Stuff', 'Stuf', 12, 15];
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query']);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->update('Article')->set('title', 'Stuff')->where('title', 'Stuf')->where('authorId', [12, 15])->run();
    }

    public function testSetOptions()
    {
        $q = $this->getMock('SimpleAR\Database\Query', ['__call']);
        $q->expects($this->exactly(3))->method('__call');

        $qb = new QueryBuilder;
        $qb->setQuery($q);

        $qb->setOptions(['limit' => 3, 'offset' => 12, 'orderBy' => 'name']);
    }

    public function testOne()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $q = $this->getMock('\SimpleAR\Database\Query', ['__call', 'run'], [null, $conn]);
        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['newQuery']);
        $qb->setConnection($conn);
        $qb->expects($this->once())->method('newQuery')->will($this->returnValue($q));

        $row = ['title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5];
        $conn->expects($this->once())->method('getNextRow')->will($this->returnValue($row));
        $q->expects($this->exactly(2))->method('__call')->withConsecutive(
            ['root', ['Article', null]],
            ['get', [['*']]]
        )->will($this->returnValue($q));
        $q->expects($this->once())->method('run');

        $article  = $qb->setRoot('Article')->one();
        $expected = ['title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5];

        $this->assertInstanceOf('Article', $article);
        $this->assertSame($expected, $article->attributes());
    }

    public function testFirst()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', ['one']);
        $qb->expects($this->exactly(3))->method('one');

        $qb->setRoot('Article');
        $qb->first();
        $qb->first();
        $qb->first();
    }

    public function testLast()
    {
        $qb = new QueryBuilder;
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $qb->setConnection($conn);

        $row = ['title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5];
        $conn->expects($this->once())->method('getNextRow')->with(false)->will($this->returnValue($row));

        $article  = $qb->setRoot('Article')->last();
        $expected = ['title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5];

        $this->assertInstanceOf('Article', $article);
        $this->assertSame($expected, $article->attributes());
    }

    public function testAll()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $q = $this->getMock('SimpleAR\Database\Query', ['__call', 'run'], [null, $conn]);
        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['newQuery']);
        $qb->setConnection($conn);
        $qb->expects($this->once())->method('newQuery')->will($this->returnValue($q));

        $return[] = ['id' => 5, 'title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2];
        $return[] = ['id' => 11, 'title' => 'My Book', 'authorId' => 1, 'blogId' => 4];
        $return[] = ['id' => 7, 'title' => 'Peter Pan', 'authorId' => 15, 'blogId' => 2];

        $conn->expects($this->exactly(4))->method('getNextRow')->with(true)->will($this->onConsecutiveCalls(
            $return[0], $return[1], $return[2], false
        ));
        $q->expects($this->exactly(2))->method('__call')->withConsecutive(
            ['root', ['Article', null]],
            ['get', [['*']]]
        )->will($this->returnValue($q));
        $q->expects($this->once())->method('run');

        $articles = $qb->setRoot('Article')->all();
        foreach ($articles as $i => $article)
        {
            $this->assertInstanceOf('Article', $article);
            $this->assertSame($return[$i], $article->attributes());
        }

    }

    public function testCount()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getColumn']);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $sql = 'SELECT COUNT(*) FROM `articles`';
        $conn->expects($this->once())->method('getColumn')->with(0)->will($this->returnValue(12));
        $conn->expects($this->once())->method('query')->with($sql, []);

        $this->assertEquals(12, $qb->setRoot('Article')->count());

        // With values to bind.
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getColumn']);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $sql = 'SELECT COUNT(*) FROM `articles` WHERE `title` = ?';
        $conn->expects($this->once())->method('getColumn')->with(0)->will($this->returnValue(12));
        $conn->expects($this->once())->method('query')->with($sql, ['Yo']);

        $this->assertEquals(12, $qb->setRoot('Article')->where('title', 'Yo')->count());
    }

    public function testModelConstructWithEagerLoad()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $return[] = [
            'id' => 5,
            'title' => 'Das Kapital',
            'authorId' => 12,
            'blogId' => 2,
            'author.id' => 12,
            'author.firstName' => 'Karl',
            'author.lastName' => 'Marx',
            'blog.id' => 2,
            'blog.title' => 'My Nice Blog',
        ];
        $return[] = [
            'id' => 6,
            'title' => 'What about Foo?',
            'authorId' => 13,
            'blogId' => 2,
            'author.id' => 13,
            'author.firstName' => 'John',
            'author.lastName' => 'Doe',
            'blog.id' => 2,
            'blog.title' => 'My Nice Blog',
        ];

        $conn->expects($this->exactly(3))->method('getNextRow')->will($this->onConsecutiveCalls(
            $return[0], $return[1], false
        ));

        // with() method will set a flag to true to make the query builder
        // parse eager loaded models.
        $articles = $qb->setRoot('Article')->with('author', 'blog')->all();
        foreach ($articles as $i => $article)
        {
            $this->assertInstanceOf('Article', $article);
            $this->assertTrue(isset($article->author));
            $this->assertInstanceOf('Author', $article->author);
            $this->assertEquals($return[$i]['author.firstName'], $article->author->firstName);
            $this->assertTrue(isset($article->blog));
            $this->assertInstanceOf('Blog', $article->blog);
            $this->assertEquals($return[$i]['blog.id'], $article->blog->id);
        }
    }

    public function testModelConstructWithDeepEagerLoad()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $return[] = array(
            'id' => 2,
            'title' => 'My Nice Blog',
            'description' => ' A blog where I say stuff.',
            'articles.id' => 5,
            'articles.title' => 'What about Bar?',
            'articles.authorId' => 13,
            'articles.blogId' => 2,
            'articles.author.id' => 13,
            'articles.author.firstName' => 'Karl',
            'articles.author.lastName' => 'Marx',
        );
        $return[] = array(
            'id' => 2,
            'title' => 'My Nice Blog',
            'description' => ' A blog where I say stuff.',
            'articles.id' => 6,
            'articles.title' => 'What about Foo?',
            'articles.authorId' => 13,
            'articles.blogId' => 2,
            'articles.author.id' => 13,
            'articles.author.firstName' => 'Karl',
            'articles.author.lastName' => 'Marx',
        );
        $return[] = array(
            'id' => 2,
            'title' => 'My Nice Blog',
            'description' => ' A blog where I say stuff.',
            'articles.id' => 7,
            'articles.title' => 'Article 3.',
            'articles.authorId' => 15,
            'articles.blogId' => 2,
            'articles.author.id' => 15,
            'articles.author.firstName' => 'John',
            'articles.author.lastName' => 'Doe',
        );
        $return[] = array(
            'id' => 3,
            'title' => 'Blog 2',
            'description' => 'A blog where on the Internet',
            'articles.id' => 100,
            'articles.title' => 'Huh?',
            'articles.authorId' => 1,
            'articles.blogId' => 3,
            'articles.author.id' => 1,
            'articles.author.firstName' => 'First',
            'articles.author.lastName' => 'Last',
        );

        $conn->expects($this->exactly(5))->method('getNextRow')->will($this->onConsecutiveCalls(
            $return[0], $return[1], $return[2], $return[3], false
        ));

        // with() method will set a flag to true to make the query builder
        // parse eager loaded models.
        $blogs = $qb->setRoot('Blog')->with('articles/author')->all();
        $this->assertCount(2, $blogs);

        $first = $blogs[0];
        $this->assertInstanceOf('Blog', $first);
        $this->assertEquals('My Nice Blog', $first->title);
        $this->assertCount(3, $first->articles);
        $this->assertEquals('What about Foo?', $first->articles[1]->title);
        $this->assertEquals('Article 3.', $first->articles[2]->title);
        $this->assertEquals('Doe', $first->articles[2]->author->lastName);

        $sec = $blogs[1];
        $this->assertInstanceOf('Blog', $sec);
        $this->assertEquals('Blog 2', $sec->title);
        $this->assertEquals('First', $sec->articles[0]->author->firstName);
    }

    /**
     * @covers ::whereHas
     */
    public function testWhereHas()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->setRoot('Blog')->whereHas('articles')->get(['*'], false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE EXISTS (SELECT `articles`.* FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id`)';
        $this->assertEquals($sql, $qb->getQuery()->build()->getSql());
    }

    /**
     * @covers ::has
     */
    public function testHasWithCount()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->setRoot('Blog')->whereHas('articles', '>', 3)->get(['*']);

        //$sql = 'SELECT * FROM `blogs` `_` WHERE (SELECT COUNT(*) FROM `articles` `__` WHERE `__`.`blog_id` = `_`.`id`) > ?';
        $sql = 'SELECT (SELECT COUNT(*) FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id`) AS `#articles`,`_`.`name` AS `name`,`_`.`description` AS `description`,`_`.`created_at` AS `created_at`,`_`.`id` AS `id` FROM `blogs` `_` WHERE `#articles` > ?';
        $val[] = 3;
        $this->assertEquals($sql, $qb->getQuery()->build()->getSql());
        $this->assertEquals($val, $qb->getQuery()->getValues());
    }

    public function testHasWithQuery()
    {
        $qb = new QueryBuilder;

        $qb->setRoot('Blog')->has('articles', function ($q) {
            $q->where('authorId', 12);
        })->get(['*'], false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE EXISTS (SELECT `articles`.* FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id` AND `articles`.`author_id` = ?)';
        $val = [[12]];
        $q = $qb->getQuery()->compile();
        $this->assertEquals($sql, $q->getSql());
        $this->assertEquals($val, $q->getValues());
    }

    /**
     * @covers ::hasNot
     */
    public function testHasNot()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow']);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->setRoot('Blog')->hasNot('articles')->get(['*'], false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE NOT EXISTS (SELECT `articles`.* FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id`)';
        $this->assertEquals($sql, $qb->getQuery()->build()->getSql());
    }

    public function testHasRecursive()
    {
        $qb = new QueryBuilder;

        $qb->setRoot('Blog')->has('articles', function ($q) {
            $q->whereHas('readers');
        })->get(['*'], false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE EXISTS (SELECT `articles`.* FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id` AND EXISTS (SELECT `articles.readers`.* FROM `USERS` `articles.readers` INNER JOIN `articles_USERS` `articles.readers_m` ON `articles.readers`.`id` = `articles.readers_m`.`user_id` WHERE `articles.readers_m`.`article_id` = `articles`.`id`))';
        $val = [];
        $q = $qb->getQuery()->compile();
        $this->assertEquals($sql, $q->getSql());
        $this->assertEquals($val, $q->getValues());
    }

    public function testHasRecursiveWithShortcutSyntax()
    {
        $qb = new QueryBuilder;

        $qb->setRoot('Blog')->whereHas('articles/readers')->get(['*'], false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE EXISTS (SELECT `articles`.* FROM `articles` `articles` WHERE `articles`.`blog_id` = `_`.`id` AND EXISTS (SELECT `articles.readers`.* FROM `USERS` `articles.readers` INNER JOIN `articles_USERS` `articles.readers_m` ON `articles.readers`.`id` = `articles.readers_m`.`user_id` WHERE `articles.readers_m`.`article_id` = `articles`.`id`))';
        $val = [];
        $q = $qb->getQuery()->compile();
        $this->assertEquals($sql, $q->getSql());
        $this->assertEquals($val, $q->getValues());
    }

    public function testSetRoot()
    {
        $qb = new QueryBuilder();

        $qb->setRoot('Article');
        $this->assertEquals('Article', $qb->getRoot());

        $qb->setRoot('Blog');
        $this->assertEquals('Blog', $qb->getRoot());
    }

    public function testWhereCallsScope()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['__call']);
        $qb->expects($this->once())->method('__call')->with('where', ['sex', 1]);

        $qb->setRoot('Author')->applyScope('women');

        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['__call']);
        $qb->expects($this->exactly(2))->method('__call')
            ->withConsecutive(
                ['where', ['isOnline', true]],
                ['where', ['isValidated', true]]
            )->will($this->returnValue($qb));

        $qb->setRoot('Article')->applyScope('status', 2);
    }

    public function testFindMany()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['setOptions', 'all']);

        $options = ['conditions' => [
            'id' => 12,
        ]];

        $qb->expects($this->once())->method('setOptions')->with($options);
        $qb->expects($this->once())->method('all');
        $qb->findMany($options);
    }

    public function testSearch()
    {
        $q = $this->getMock('\SimpleAR\Database\Query', ['__call'], [new SelectBuilder]);
        $q->expects($this->any())->method('__call')->with('limit', [10, 20]);

        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['all', 'count']);
        $qb->expects($this->once())->method('all')->will($this->returnValue(['array']));
        $qb->expects($this->once())->method('count')->will($this->returnValue(34));
        $qb->setQuery($q);

        $res = $qb->search(3, 10);
        $this->assertEquals(['rows' => ['array'], 'count' => 34], $res);
    }

    public function testLoadRelation()
    {
        $relation = Blog::relation('articles');
        $blogs[] = new Blog(['id' => 1]);
        $blogs[] = new Blog(['id' => 2]);
        $blogs[] = new Blog(['id' => 3]);
        $articles[] = new Article(['id' => 1, 'blogId' => 1]);
        $articles[] = new Article(['id' => 2, 'blogId' => 2]);
        $articles[] = new Article(['id' => 3, 'blogId' => 3]);
        $articles[] = new Article(['id' => 4, 'blogId' => 3]);
        $articles[] = new Article(['id' => 5, 'blogId' => 2]);

        $q = $this->getMock('SimpleAR\Database\Query', ['whereTuple']);
        $q->expects($this->once())->method('whereTuple')->with(['blogId'], [[1],[2],[3]]);

        $qb = m::mock('SimpleAR\Orm\Builder[select,setOptions,all]');
        $qb->shouldReceive('select')->once()->withNoArgs()->andReturn($q);
        $qb->shouldReceive('setOptions')->once()->with(['conditions' => []]);
        $qb->shouldReceive('all')->once()->with(['*'], $q)->andReturn($articles);
        $this->assertEquals($articles, $qb->loadRelation($relation, $blogs));
    }

    public function testPreloadRelations()
    {
        $relation = Blog::relation('articles');
        $blogs[] = $b1 = new Blog(['id' => 1]);
        $blogs[] = $b2 = new Blog(['id' => 2]);
        $blogs[] = $b3 = new Blog(['id' => 3]);
        $articles[] = $a1 = new Article(['id' => 1, 'blogId' => 1]);
        $articles[] = $a2 = new Article(['id' => 2, 'blogId' => 2]);
        $articles[] = $a3 = new Article(['id' => 3, 'blogId' => 3]);
        $articles[] = $a4 = new Article(['id' => 4, 'blogId' => 3]);
        $articles[] = $a5 = new Article(['id' => 5, 'blogId' => 2]);

        $qb = $this->getMock('SimpleAR\Orm\Builder', ['loadRelation']);
        $qb->expects($this->once())->method('loadRelation')
            ->with($relation, $blogs)
            ->will($this->returnValue($articles));

        $qb->setRoot('Blog');
        $qb->preloadRelation($blogs, 'articles');
        $this->assertEquals([$a1], $b1->articles);
        $this->assertEquals([$a2, $a5], $b2->articles);
        $this->assertEquals([$a3, $a4], $b3->articles);
    }

}
