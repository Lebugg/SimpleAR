<?php

class ModelTest extends PHPUnit_Extensions_Database_TestCase
{
    private static $_cfg;
    private static $_db;

    private static function _initializeSimpleAR()
    {
        self::$_cfg = $cfg = new SimpleAR\Config();
        $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/db.json'), true);
        $cfg->doForeignKeyWork = true;
        $cfg->debug            = true;
        $cfg->modelDirectory   = __DIR__ . '/models/';
        $cfg->dateFormat       = 'd/m/Y';

        self::$_db = SimpleAR\init($cfg);
    }

    public static function setUpBeforeClass()
    {
        self::_initializeSimpleAR();
    }

    public function getConnection()
    {
        return $this->createDefaultDBConnection(self::$_db->pdo(), self::$_db->database());
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

    /**
     * @expectedException SimpleAR\Exception
     */
    public function testDeleteInstanceOnNewModel()
    {
        // Cannot delete a new model instance.
        $blog = new Blog();
        $blog->delete();
    }
}
