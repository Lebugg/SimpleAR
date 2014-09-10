<?php

use \Mockery as m;

use \SimpleAR\DateTime;
use \SimpleAR\Facades\DB;
use \SimpleAR\Facades\Cfg;
use \SimpleAR\Orm\Table;
use \SimpleAR\Orm\Builder as QueryBuilder;

class ModelTest extends PHPUnit_Framework_TestCase
{
    public function testAttributeManipulation()
    {
        $stub = $this->getMockForAbstractClass('SimpleAR\Orm\Model');

        $stub->foo = 'bar';
        $this->assertEquals($stub->foo, 'bar');

        // Test __isset() and __unset().
        $this->assertTrue(isset($stub->foo));
        unset($stub->foo);
        $this->assertFalse(isset($stub->foo));
    }

    public function testIsDirtyFlag()
    {
        $conn = m::mock('\SimpleAR\Database\Connection[query,lastInsertId]');
        $conn->shouldReceive('lastInsertId')->once()->andReturn(12);
        DB::setConnection($conn);

        $article = new Article;

        $this->assertFalse($article->isDirty());
        $article->foo = 'bar';
        $this->assertTrue($article->isDirty());
        $article->save();
        $this->assertFalse($article->isDirty());
    }

    public function testGetColumns()
    {
        $expected = array('name', 'description', 'created_at', 'id');
        $this->assertEquals($expected, array_values(Blog::columns()));

        $expected = array('blog_id', 'author_id', 'title', 'created_at', 'id');
        $this->assertEquals($expected, array_values(Article::columns()));
    }

    /**
     * @expectedException SimpleAR\Exception
     */
    public function testDeleteInstanceOnNewModel()
    {
        // Cannot delete a new model instance.
        $blog = new Blog();
        $blog->delete();
    }

    public function testToArray()
    {
        $blog  = new Blog(array('name' => 'foo', 'url' => 'bar@bar.com'));
        $array = $blog->toArray();

        $this->assertTrue(is_array($array));
        $this->assertEquals('foo', $array['name']);
        $this->assertEquals('bar@bar.com', $array['url']);

        $blog->dateCreation = new SimpleAR\DateTime();
        SimpleAR\DateTime::setFormat('Y-m-d');
        $array = $blog->toArray();
        $this->assertEquals(date('Y-m-d'), $array['dateCreation']);

        SimpleAR\DateTime::setFormat('d/m/Y');
        $array = $blog->toArray();
        $this->assertEquals(date('d/m/Y'), $array['dateCreation']);
    }

    public function testSet()
    {
        $blog = new Blog();
        $blog->set(array('name' => 'foo', 'url' => 'bar@baz.com'));

        $this->assertEquals('foo', $blog->name);
    }

    public function testGetter()
    {
        $stub = $this->getMock('Blog', ['get_x']);

        $stub->expects($this->exactly(2))
             ->method('get_x');

        $stub->x;
        $stub->x;
    }

    public function testSetter()
    {
        $stub = $this->getMock('Blog', ['set_x']);

        $stub->expects($this->exactly(2))
            ->method('set_x')
            ->withConsecutive([12], [15]);

        $stub->x = 12;
        $stub->x = 15;
    }

    public function testTablesStorage()
    {
        Article::wakeup();

        $this->assertInstanceOf('SimpleAR\Orm\Table', Article::table());
    }

    /**
     * @TODO Move findByPK in Orm\Builder before use this test.
     */
    public function no_testFindByPKWithSimplePK()
    {
        $t = new Table('models', ['id']);

        $m = '\SimpleAR\Orm\Model';
        $m::setTable($m, $t);

        // - - -

        // Expects one().
        $q = $this->getMock('\SimpleAR\Orm\Builder');
        $q->expects($this->exactly(2))->method('one')->will($this->returnValue(true));
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $m::setQueryBuilder($q);
        $m::findByPK(12);
        $m::findByPK('12');

        // Expects all().
        $q = $this->getMock('\SimpleAR\Orm\Builder');
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $q->expects($this->exactly(2))->method('all')->will($this->returnValue(true));
        $m::setQueryBuilder($q);
        $m::findByPK(array(1, 2));
        $m::findByPK(array(1, 2, 3));
    }

    /**
     * @TODO Move findByPK in Orm\Builder before use this test.
     */
    public function no_testFindByPKWithCompoundPK()
    {
        $t = new Table('models', array('a1', 'a2'));

        $m = 'SimpleAR\Orm\Model';
        $m::setTable($m, $t);

        // Expects one().
        $q = $this->getMock('SimpleAR\Orm\Builder');
        $q->expects($this->exactly(4))->method('one')->will($this->returnValue(true));
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $m::setQueryBuilder($q);
        $m::findByPK(12);
        $m::findByPK('12');
        $m::findByPK(array('12'));
        $m::findByPK(array(12, 'a'));

        // Expects all().
        $q = $this->getMock('SimpleAR\Orm\Builder');
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $q->expects($this->exactly(2))->method('all')->will($this->returnValue(true));
        $m::setQueryBuilder($q);
        $m::findByPK(array(array(1, 'a')));
        $m::findByPK(array(array(1, 'a'), array(2, 'b')));
        $q = $this->getMock('SimpleAR\Orm\Builder');
    }

    public function testThatCorrectRootIsSetForNewQuery()
    {
        $qb = new QueryBuilder('Article');
        $this->assertEquals($qb, Article::query());

        $qb = new QueryBuilder('Blog', 'b');
        $this->assertEquals($qb, Blog::query(null, 'b'));
    }

    public function no_testExistsCallsFind()
    {
        $qb = m::mock('\SimpleAR\Orm\Builder');
        $qb->shouldReceive('findOne')->with(['conditions' => ['id' => 12]])->andReturn(true);

        $mock = m::mock('Blog[query]');
        $mock::shouldReceive('query')->once()->andReturn($qb);

        $this->assertTrue(User::exists(12));
    }

    public function no_testExistsByConditionsCallsFind()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('findOne'));

        $opt = array('conditions' => array('name' => 'yo!'));
        $qb->expects($this->once())->method('findOne')->with($opt)->will($this->returnValue(true));

        Article::exists(array('name' => 'yo!'), false);
    }

    public function testIsConcrete()
    {
        $a = new Article();
        $this->assertFalse($a->isConcrete());

        // $a->save();
        // $this->assertTrue($a->isConcrete());

        $a = new Article();
        $this->assertFalse($a->isConcrete());
        $a->populate(array('id' => 12, 'title' => 'Title'));
        $this->assertTrue($a->isConcrete());
    }

    public function testPopulateSetNonColumnAttributesToo()
    {
        $data = array(
            'id' => 12,
            'name' => 'The best blog ever read.',
            '#articles' => 76,
        );

        $b = new Blog();
        $b->populate($data);

        $this->assertEquals('The best blog ever read.', $b->name);
        $this->assertEquals(76, $b->{'#articles'});
    }

    public function testHasScope()
    {
        $this->assertTrue(Author::hasScope('women'));
        $this->assertFalse(Author::hasScope('men'));
    }

    public function no_testScope()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('__call'));
        $qb->expects($this->once())->method('__call')
            ->with('where', array('sex', 1))->will($this->returnValue($qb));

        Author::setQueryBuilder($qb);
        Author::women();

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('__call'));
        $qb->expects($this->exactly(2))->method('__call')
            ->withConsecutive(
                array('where', array('isOnline', true)),
                array('where', array('isValidated', true))
            )->will($this->returnValue($qb));

        Article::setQueryBuilder($qb);
        Article::status(2);
    }

    public function no_testLoadRelation()
    {
        // With a *-to-many relation.
        $articles = [new Article, new Article];

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('all'));
        $qb->expects($this->once())->method('all')->will($this->returnValue($articles));
        Blog::setQueryBuilder($qb);

        $blog = new Blog();
        $blog->populate(array('id' => 12)); // In order to be concrete.
        $blog->load('articles');

        $attributes = $blog->attributes();
        $this->assertArrayHasKey('articles', $attributes);
        $this->assertEquals($articles, $attributes['articles']);

        // With a *-to-one relation.
        // QueryBuilder::loadRelation always return an array.
        $authors = array(
            new Author,
        );

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('loadRelation'));
        $qb->expects($this->once())->method('loadRelation')->will($this->returnValue($authors));
        Article::setQueryBuilder($qb);

        $article = new Article();
        $article->populate(array('id' => 12)); // In order to be concrete.
        $article->load('author');

        $attributes = $article->attributes();
        $this->assertArrayHasKey('author', $attributes);
        $this->assertEquals($authors[0], $attributes['author']);
    }

    public function no_testLoadRelationManyMany()
    {
        $users = array(
            new User,
            new User,
        );

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('all'));
        $qb->expects($this->once())->method('all')->will($this->returnValue($users));
        Article::setQueryBuilder($qb);

        $article = new Article();
        $article->populate(array('id' => 12)); // In order to be concrete.
        $article->load('readers');

        $attributes = $article->attributes();
        $this->assertArrayHasKey('readers', $attributes);
        $this->assertEquals($users, $attributes['readers']);

        $relation = Article::relation('readers');
        $reversed = User::relation('readers_r');
        $this->assertInstanceOf('SimpleAR\Orm\Relation\ManyMany', $reversed);
        $this->assertEquals($relation->reverse(), $reversed);
    }

    public function no_testLoadRelationWithScope()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', ['applyScopes', 'all']);
        $scope = [
            'status' => [2],
            'recent' => [],
        ];
        $qb->expects($this->once())
            ->method('applyScopes')
            ->with($scope);
        Article::setQueryBuilder($qb);

        $blog = new Blog();
        $blog->populate(['id' => 12]); // In order to be concrete.
        $blog->load('onlineArticles');
    }

    public function no_testAll()
    {
        $expected = [new Article, new Article];
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('all'));
        $qb->expects($this->once())->method('all')->will($this->returnValue($expected));
        Article::setQueryBuilder($qb);

        $res = Article::all();
        $this->assertEquals($expected, $res);
    }

    public function no_testSearch()
    {
        $articles = [new Article, new Article];
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('count', 'all'));
        $qb->expects($this->once())->method('count')->will($this->returnValue(2));
        $qb->expects($this->once())->method('all')->will($this->returnValue($articles));
        Article::setQueryBuilder($qb);

        $res = Article::search([], 1, 10);
        $expected = ['count' => 2, 'rows' => $articles];
        $this->assertEquals($expected, $res);
    }

    public function testGetID()
    {
        $a = new Article;
        $a->id = 2;

        $this->assertEquals([2], $a->id());

        Article::table()->primaryKey = ['id', 'blogId'];

        $a->id = 2;
        $a->blogId = 12;
        $this->assertEquals([2, 12], $a->id());

        Article::table()->primaryKey = ['id', 'title'];
        $a->id = 2;
        $a->title = 'string';
        $this->assertEquals([2, 'string'], $a->id());

        // Reset.
        Article::table()->primaryKey = ['id'];
    }

    public function testDateAttributesConversion()
    {
        $a = new Article;
        $a->dateCreation = '2014-06-12';

        $a->convertDateAttributesToObject();

        $exp = new DateTime('2014-06-12');
        $this->assertEquals($exp, $a->dateCreation);
    }

    public function no_testCountMethodFiltersPassedOptions()
    {
        $opts = ['conditions' => [], 'having' => [], 'groupBy' => []];
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('setOptions', 'count'));
        $qb->expects($this->once())->method('setOptions')->with($opts)->will($this->returnValue($qb));
        $qb->expects($this->once())->method('count');
        Article::setQueryBuilder($qb);

        $opts['select'] = ['title', 'stuff'];
        $opts['orderBy'] = ['#readers'];
        Article::count($opts);
    }
}
