<?php

use SimpleAR\Orm\Builder as QueryBuilder;
use SimpleAR\Database\Builder\SelectBuilder;
use SimpleAR\Database\Compiler\BaseCompiler;

class BuilderTest extends PHPUnit_Framework_TestCase
{
    /**
      Check that QueryBuilder uses objects stored in Database class.
     *
     */
    public function testDatabaseGetter()
    {
        global $sar;
        $qb = new QueryBuilder();

        $this->assertEquals($sar->db->getCompiler(), $qb->getCompiler());
        $this->assertEquals($sar->db->getConnection(), $qb->getConnection());
    }

    public function testInsert()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', array('newQuery'), array('Article'));

        $q = $this->getMock('SimpleAR\Database\Query', array('__call', 'getConnection', 'run'));
        $conn = $this->getMock('SimpleAR\Database\Connection', array('lastInsertId'));
        $conn->expects($this->once())->method('lastInsertId')->will($this->returnValue(12));
        $q->expects($this->any())->method('getConnection')->will($this->returnValue($conn));

        $qb->expects($this->once())->method('newQuery')
            ->with($this->isInstanceOf('SimpleAR\Database\Builder\InsertBuilder'))
            ->will($this->returnValue($q));

        $q->expects($this->at(0))->method('__call')->with('root', array('Article'))->will($this->returnValue($q));
        $q->expects($this->at(1))->method('__call')->with('fields', array(array('title', 'authorId')))->will($this->returnValue($q));
        $q->expects($this->at(2))->method('__call')->with('values', array(array('Stuff', 15)))->will($this->returnValue($q));
        $q->expects($this->once())->method('run')->will($this->returnValue($q));

        $lastInsertId = $qb->insert(array('title', 'authorId'), array('Stuff', 15));
        $this->assertEquals(12, $lastInsertId);
    }

    public function testInsertInto()
    {
        $sql = 'INSERT INTO `articles` (`title`,`author_id`) VALUES(?,?)';
        $val = array('Stuff', 12);
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query'));
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->insertInto('articles', array('title', 'author_id'))->values(array('Stuff', 12))->run();
    }

    public function testDeleteWhere()
    {
        $sql = 'DELETE FROM `articles` WHERE `id` = ?';
        $val = array(12);
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query'));
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);
        $qb->delete('Article')->where('id', 12)->run();
    }

    public function testDeleteSetRootIfRootHasBeenCalled()
    {
        $qb = new QueryBuilder;
        $query = $qb->root('Article')->delete();

        $this->assertEquals('Article', $query->getBuilder()->getRootModel());
    }

    public function testUpdate()
    {
        $sql = 'UPDATE `articles` SET `title` = ? WHERE `title` = ? AND `author_id` IN (?,?)';
        $val = array('Stuff', 'Stuf', 12, 15);
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query'));
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->update('Article')->set('title', 'Stuff')->where('title', 'Stuf')->where('authorId', array(12, 15))->run();
    }

    public function testSetOptions()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', array('newQuery'));
        $q = $this->getMock('SimpleAR\Database\Query', array('__call'));
        $qb->expects($this->once())->method('newQuery')->will($this->returnValue($q));

        $q->expects($this->exactly(3))->method('__call');
        $qb->setOptions(array('limit' => 3, 'offset' => 12, 'orderBy' => 'name'));
    }

    public function testOne()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $q = $this->getMock('SimpleAR\Database\Query', ['__call', 'run'], [null, null, $conn]);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);
        $qb->setQuery($q);

        $row = array('title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5);
        $conn->expects($this->once())->method('getNextRow')->will($this->returnValue($row));
        $q->expects($this->exactly(2))->method('__call')->withConsecutive(
            ['root', ['Article']],
            ['select', [['*']]]
        )->will($this->returnValue($q));
        $q->expects($this->once())->method('run');

        $article  = $qb->root('Article')->one();
        $expected = array('title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5);

        $this->assertInstanceOf('Article', $article);
        $this->assertSame($expected, $article->attributes());
    }

    public function testFirst()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', array('one'));
        $qb->expects($this->exactly(3))->method('one');

        $qb->root('Article');
        $qb->first();
        $qb->first();
        $qb->first();
    }

    public function testLast()
    {
        $qb = new QueryBuilder;
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb->setConnection($conn);

        $row = array('title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5);
        $conn->expects($this->once())->method('getNextRow')->with(false)->will($this->returnValue($row));

        $article  = $qb->root('Article')->last();
        $expected = array('title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2, 'id' => 5);

        $this->assertInstanceOf('Article', $article);
        $this->assertSame($expected, $article->attributes());
    }

    public function testAll()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $q = $this->getMock('SimpleAR\Database\Query', ['__call', 'run'], [null, null, $conn]);
        $qb = new QueryBuilder;
        $qb->setConnection($conn);
        $qb->setQuery($q);

        $return[] = array('id' => 5, 'title' => 'Das Kapital', 'authorId' => 12, 'blogId' => 2);
        $return[] = array('id' => 11, 'title' => 'My Book', 'authorId' => 1, 'blogId' => 4);
        $return[] = array('id' => 7, 'title' => 'Peter Pan', 'authorId' => 15, 'blogId' => 2);

        $conn->expects($this->exactly(4))->method('getNextRow')->with(true)->will($this->onConsecutiveCalls(
            $return[0], $return[1], $return[2], false
        ));
        $q->expects($this->exactly(2))->method('__call')->withConsecutive(
            ['root', ['Article']],
            ['select', [['*']]]
        )->will($this->returnValue($q));
        $q->expects($this->once())->method('run');

        $articles = $qb->root('Article')->all();
        foreach ($articles as $i => $article)
        {
            $this->assertInstanceOf('Article', $article);
            $this->assertSame($return[$i], $article->attributes());
        }

    }

    public function testCount()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getColumn'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $sql = 'SELECT COUNT(*) FROM `articles`';
        $conn->expects($this->once())->method('getColumn')->with(0)->will($this->returnValue(12));
        $conn->expects($this->once())->method('query')->with($sql, array());

        $this->assertEquals(12, $qb->root('Article')->count());

        // With values to bind.
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getColumn'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $sql = 'SELECT COUNT(*) FROM `articles` WHERE `title` = ?';
        $conn->expects($this->once())->method('getColumn')->with(0)->will($this->returnValue(12));
        $conn->expects($this->once())->method('query')->with($sql, array('Yo'));

        $this->assertEquals(12, $qb->root('Article')->where('title', 'Yo')->count());
    }

    public function testModelConstructWithEagerLoad()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $return[] = array(
            'id' => 5,
            'title' => 'Das Kapital',
            'authorId' => 12,
            'blogId' => 2,
            'author.id' => 12,
            'author.firstName' => 'Karl',
            'author.lastName' => 'Marx',
            'blog.id' => 2,
            'blog.title' => 'My Nice Blog',
        );
        $return[] = array(
            'id' => 6,
            'title' => 'What about Foo?',
            'authorId' => 13,
            'blogId' => 2,
            'author.id' => 13,
            'author.firstName' => 'John',
            'author.lastName' => 'Doe',
            'blog.id' => 2,
            'blog.title' => 'My Nice Blog',
        );

        $conn->expects($this->exactly(3))->method('getNextRow')->will($this->onConsecutiveCalls(
            $return[0], $return[1], false
        ));

        // with() method will set a flag to true to make the query builder 
        // parse eager loaded models.
        $articles = $qb->root('Article')->with('author', 'blog')->all();
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
        $blogs = $qb->root('Blog')->with('articles/author')->all();
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

    public function testHas()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->root('Blog')->has('articles')->select(array('*'), false);

        $sql = 'SELECT `_`.* FROM `blogs` `_` WHERE EXISTS (SELECT `__`.* FROM `articles` `__` WHERE `__`.`blog_id` = `_`.`id`)';
        $this->assertEquals($sql, $qb->getQuery()->build()->getSql());
    }

    public function testHasWithCount()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', array('query', 'getNextRow'));
        $qb = new QueryBuilder;
        $qb->setConnection($conn);

        $qb->root('Blog')->has('articles', '>', 3)->select(['*']);

        //$sql = 'SELECT * FROM `blogs` `_` WHERE (SELECT COUNT(*) FROM `articles` `__` WHERE `__`.`blog_id` = `_`.`id`) > ?';
        $sql = 'SELECT (SELECT COUNT(*) FROM `articles` `__` WHERE `__`.`blog_id` = `_`.`id`) AS `#articles`,`_`.`name` AS `name`,`_`.`description` AS `description`,`_`.`created_at` AS `created_at`,`_`.`id` AS `id` FROM `blogs` `_` WHERE `#articles` > ?';
        $val[] = 3;
        $this->assertEquals($sql, $qb->getQuery()->build()->getSql());
        $this->assertEquals($val, $qb->getQuery()->getValues());
    }

    public function testSetRoot()
    {
        $qb = new QueryBuilder();

        $qb->root('Article');
        $this->assertEquals('Article', $qb->getRoot());

        $qb->root('Blog');
        $this->assertEquals('Blog', $qb->getRoot());
    }

    public function testWhereCallsScope()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('__call'));
        $qb->expects($this->once())->method('__call')->with('where', array('sex', 1));

        $qb->root('Author')->applyScope('women');

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('__call'));
        $qb->expects($this->exactly(2))->method('__call')
            ->withConsecutive(
                array('where', array('isOnline', true)),
                array('where', array('isValidated', true))
            )->will($this->returnValue($qb));

        $qb->root('Article')->applyScope('status', 2);
    }

    public function testFindMany()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('setOptions', 'all'));

        $options = array('conditions' => array(
            'id' => 12,
        ));

        $qb->expects($this->once())->method('setOptions')->with($options);
        $qb->expects($this->once())->method('all');
        $qb->findMany($options);
    }

    public function testSearch()
    {
        $b = $this->getMock('SimpleAR\Database\Builder', ['build']);
        $b->expects($this->exactly(2))->method('build');

        $c = $this->getMock('SimpleAR\Database\Compiler', ['compile']);
        $c->expects($this->exactly(2))->method('compile')->will($this->returnValue(['SQL', []]));

        $conn = $this->getMock('SimpleAR\Database\Connection', ['query', 'getNextRow', 'getColumn']);
        $conn->expects($this->exactly(2))->method('query');

        $q = $this->getMock('SimpleAR\Database\Query', ['getConnection'], [$b, $c, $conn]);
        $q->expects($this->any())->method('getConnection')->will($this->returnValue($conn));

        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['getQuery']);
        $qb->expects($this->any())->method('getQuery')->will($this->returnValue($q));

        $qb->search(2, 10);
    }

    public function testGet()
    {
        $qb = $this->getMock('SimpleAR\Orm\Builder', ['__call', 'all']);
        $qb->expects($this->once())->method('__call')->with('limit', [10]);
        $qb->expects($this->once())->method('all');
        $qb->get(10);

        $qb = $this->getMock('SimpleAR\Orm\Builder', ['__call', 'all']);
        $qb->expects($this->never())->method('__call');
        $qb->expects($this->once())->method('all');
        $qb->get();
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

        $q = $this->getMock('SimpleAR\Database\Query', ['__call', 'orderBy', 'whereTuple', 'all']);
        $q->expects($this->once())->method('__call')->with('conditions', [[]]);
        $q->expects($this->once())->method('orderBy')->with([]);
        $q->expects($this->once())->method('whereTuple')->with(['blogId'], [[1],[2],[3]]);
        $q->expects($this->once())->method('all')->with(['*'])->will($this->returnValue($articles));

        $qb = $this->getMock('SimpleAR\Orm\Builder', ['newQuery']);
        $qb->expects($this->once())->method('newQuery')->will($this->returnValue($q));
        $qb->root('Blog');
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

        $qb->root('Blog');
        $qb->preload('articles');
        $qb->preloadRelations($blogs);
        $this->assertEquals([$a1], $b1->articles);
        $this->assertEquals([$a2, $a5], $b2->articles);
        $this->assertEquals([$a3, $a4], $b3->articles);
    }
}
