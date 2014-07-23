<?php
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL | E_STRICT);

use \SimpleAR\Facades\DB;
use \SimpleAR\Facades\Cfg;
use \SimpleAR\Orm\Table;

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
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('insert'));
        $qb->expects($this->any())->method('insert')->will($this->returnValue(12));

        $class = 'Article';
        $class::setQueryBuilder($qb);

        $stub = new $class();

        $this->assertTrue($stub->isDirty());
        $stub->foo = 'bar';
        $this->assertTrue($stub->isDirty());
        $stub->save();
        $this->assertFalse($stub->isDirty());
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
        $stub = $this->getMock('Blog', array('get_x'));

        $stub->expects($this->exactly(2))
             ->method('get_x');

        $stub->x;
        $stub->x;
    }

    public function testSetter()
    {
        $stub = $this->getMock('Blog', array('set_x'));

        $stub->expects($this->exactly(2))
            ->method('set_x')
            ->withConsecutive(array(12), array(15));

        $stub->x = 12;
        $stub->x = 15;
    }

    public function testTablesStorage()
    {
        Article::wakeup();

        $this->assertInstanceOf('SimpleAR\Orm\Table', Article::table());
    }

    public function testFindByPKWithSimplePK()
    {
        $t = new Table('models', array('id'));

        $m = 'SimpleAR\Orm\Model';
        $m::setTable($m, $t);

        // Expects one().
        $q = $this->getMock('SimpleAR\Orm\Builder');
        $q->expects($this->exactly(2))->method('one')->will($this->returnValue(true));
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $m::setQueryBuilder($q);
        $m::findByPK(12);
        $m::findByPK('12');

        // Expects all().
        $q = $this->getMock('SimpleAR\Orm\Builder');
        $q->expects($this->any())->method('setOptions')->will($this->returnValue($q));
        $q->expects($this->exactly(2))->method('all')->will($this->returnValue(true));
        $m::setQueryBuilder($q);
        $m::findByPK(array(1, 2));
        $m::findByPK(array(1, 2, 3));
    }

    public function testFindByPKWithCompoundPK()
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
        $qb = $this->getMock('SimpleAR\Orm\Builder', array('root', 'all'));

        $m = 'SimpleAR\Orm\Model';
        $m::setQueryBuilder($qb);

        $qb->expects($this->exactly(4))->method('root')->withConsecutive(
            array('Article'),
            array('Blog'),
            array('Article'),
            array('Blog')
        );

        Article::query();
        Blog::query();

        // Through __callStatic().
        Article::all();
        Blog::all();
    }

    public function testExistsCallsFind()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('findOne'));

        $opt = array('conditions' => array('id' => 12));
        $qb->expects($this->once())->method('findOne')->with($opt)->will($this->returnValue(true));

        Article::setQueryBuilder($qb);
        Article::exists(12);
    }

    public function testExistsByConditionsCallsFind()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('findOne'));

        $opt = array('conditions' => array('name' => 'yo!'));
        $qb->expects($this->once())->method('findOne')->with($opt)->will($this->returnValue(true));

        Article::setQueryBuilder($qb);
        Article::exists(array('name' => 'yo!'), false);
    }

    public function testIsConcrete()
    {
        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('insert'));
        $qb->expects($this->once())->method('insert')->will($this->returnValue(12));
        Article::setQueryBuilder($qb);

        $a = new Article();
        $this->assertFalse($a->isConcrete());

        $a->save();
        $this->assertTrue($a->isConcrete());

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

    public function testScope()
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

    public function testLoadRelation()
    {
        $articles = array(
            new Article,
            new Article,
        );

        $qb = $this->getMock('\SimpleAR\Orm\Builder', array('findMany'));
        $qb->expects($this->once())->method('findMany')->will($this->returnValue($articles));
        Blog::setQueryBuilder($qb);

        $blog = new Blog();
        $blog->populate(array('id' => 12)); // In order to be concrete.
        $blog->load('articles');

        $attributes = $blog->attributes();
        $this->assertArrayHasKey('articles', $attributes);
        $this->assertEquals($articles, $attributes['articles']);
    }
}
