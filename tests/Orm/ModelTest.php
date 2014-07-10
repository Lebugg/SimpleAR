<?php
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL | E_STRICT);

use \SimpleAR\Facades\DB;
use \SimpleAR\Facades\Cfg;

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
        $stub = $this->getMockForAbstractClass('SimpleAR\Orm\Model');

        $this->assertFalse($stub->isDirty());
        $stub->foo = 'bar';
        $this->assertTrue($stub->isDirty());
    }

    public function testGetColumns()
    {
        $expected = array('name', 'description', 'created_at');
        $this->assertEquals($expected, array_values(Blog::columns()));
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

    public function testTablesStorage()
    {
        Article::wakeup();

        $this->assertInstanceOf('SimpleAR\Orm\Table', Article::table());
    }

    public function testFindByPKWithSimplePK()
    {
        $t = $this->getMock('SimpleAR\Orm\Table', array(), array('models', 'id', array()));
        $t->isSimplePrimaryKey = true;

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
        $t = $this->getMock('SimpleAR\Orm\Table', array(), array('models', 'id', array()));
        $t->isSimplePrimaryKey = false;

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
}
