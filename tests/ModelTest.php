<?php
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL | E_STRICT);

use \SimpleAR\Facades\DB;

class ModelTest extends PHPUnit_Extensions_Database_TestCase
{
    private static $_sar;

    private static function _initializeSimpleAR()
    {
        $cfg = new SimpleAR\Config();
        $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/db.json'), true);
        $cfg->doForeignKeyWork = true;
        $cfg->debug            = true;
        $cfg->modelDirectory   = __DIR__ . '/models/';
        $cfg->dateFormat       = 'd/m/Y';

        self::$_sar = new SimpleAR($cfg);
    }

    public static function setUpBeforeClass()
    {
        self::_initializeSimpleAR();
    }

    public function getConnection()
    {
        return $this->createDefaultDBConnection(self::$_sar->db->pdo(), self::$_sar->db->database());
    }

    public function getDataSet()
    {
        return $this->createXMLDataSet(__DIR__ . '/fixtures/ModelTest.xml');
    }

    public function testAttributeManipulation()
    {
        $stub = $this->getMockForAbstractClass('SimpleAR\Model');

        $stub->foo = 'bar';
        $this->assertEquals($stub->foo, 'bar');
        
        // Test __isset() and __unset().
        $this->assertTrue(isset($stub->foo));
        unset($stub->foo);
        $this->assertFalse(isset($stub->foo));
    }

    public function testIsDirtyFlag()
    {
        $stub = $this->getMockForAbstractClass('SimpleAR\Model');

        $this->assertFalse($stub->isDirty());
        $stub->foo = 'bar';
        $this->assertTrue($stub->isDirty());
    }

    public function testFilterOption()
    {
        // By filter name.
        $blogs1 = Blog::all(array('filter' => 'restricted'));

        // By attribute array.
        $blogs2 = Blog::all(array('filter' => array('name', 'url')));

        $this->assertEquals(count($blogs1), count($blogs2), 'Different blog counts');

        foreach ($blogs1 as $i => $blog1)
        {
            // Should be equal.
            $this->assertEquals($blog1->attributes(), $blogs2[$i]->attributes(), 'Fail in filter use.');
        }
    }

    public function testGetColumns()
    {
        $expected = array('description', 'name', 'url');
        $this->assertEquals($expected, array_values(Blog::columns()));
    }

    public function testCount()
    {
        $a = $this->getConnection()->getRowCount('blog');
        $b = Blog::count();

        $this->assertEquals($a, $b);
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

    public function testOrderByRand()
    {
        $blogs = Blog::all(array('order_by' => DB::expr('RAND()')));

        $this->assertTrue(is_array($blogs));
    }

    /**
     * @expectedException SimpleAR\Exception\RecordNotFound
     */
    public function testRecordNotFoundException()
    {
        Blog::findByPK(-1);
    }

    public function testGetter()
    {
        $stub = $this->getMock('Blog', array('get_x'));

        $stub->expects($this->exactly(2))
             ->method('get_x');

        $stub->x;
        $stub->x;
    }

    public function testVirtualAttributeConditions()
    {
        $articles = Article::all(array('conditions' => array(
            'relevant' => true,
        )));

        foreach ($articles as $article)
        {
            $this->assertContains('pokemon', $article->content);
        }
    }

    public function testExpressionInOrderByOption()
    {
        $res = Blog::all(array('order_by' => DB::expr('RAND()')));

        $this->assertTrue(is_array($res));
        $this->assertEquals($this->getConnection()->getRowCount('blog'), count($res));
    }

    public function testExpressionInConditionsOption()
    {
        $a = Article::all(array('conditions' => array(array('date_expiration', '>=', date('Y-m-d'))))); 
        $b = Article::all(array('conditions' => array(array('date_expiration', '>=', DB::expr('NOW()'))))); 

        $this->assertEquals($a, $b);
    }

    public function testCallStaticModelToQuery()
    {
        $a = Article::one(array('conditions' => array(array('date_expiration', '>=', DB::expr('NOW()'))))); 
        $b = Article::conditions(array(array('date_expiration', '>=', DB::expr('NOW()'))))->one(); 

        $this->assertEquals($a, $b);

        /* $a = Blog::all(array('filter' => array('name', 'description'))); */
        /* $b = Blog::filter('name', 'description')->all(); */ 

        /* $this->assertEquals($a, $b); */
    }

    public function testHasManyRelationFetch()
    {
        $blog     = Blog::find(1);
        $articles = $blog->articles;

        foreach ($articles as $article) {
            $this->assertEquals('Article', get_class($article));
            $this->assertEquals($article->blog_id, 1);
        }
    }
}
