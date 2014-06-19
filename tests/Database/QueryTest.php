<?php

use \SimpleAR\Database\Query;

class QueryTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));
        $sar->db->setConnection($conn);
    }

    public function testGetConnection()
    {
        global $sar;

        $stub = $this->getMockForAbstractClass('\SimpleAR\Database\Query');
        $this->assertEquals($sar->db->connection(), $stub->getConnection());
    }

    public function testRunProcess()
    {
        //$stub = $this->getMockForAbstractClass('\SimpleAR\Database\Query', array(), '', true, true, true, array('build', 'execute'));
        $query = $this->getMockForAbstractClass('\SimpleAR\Database\Query', array(), '', true, true, true, array('getBuilder', 'getCompiler'));

        $builder  = $this->getMock('\SimpleAR\Database\Builder');
        $compiler = $this->getMock('\SimpleAR\Database\Compiler');

        $query->expects($this->once())->method('getBuilder')->will($this->returnValue($builder));
        $query->expects($this->once())->method('getCompiler')->will($this->returnValue($compiler));
        $query->expects($this->once())->method('getValues')->will($this->returnValue(array()));

        $query->run();
    }

    public function testQueryIsSafe()
    {
        $query = $this->getMockForAbstractClass('\SimpleAR\Database\Query');

        $sql = 'DELETE FROM users';
        $this->assertFalse($query->queryIsSafe($sql));

        $sql = 'DELETE FROM users WHERE id = 3';
        $this->assertTrue($query->queryIsSafe($sql));
    }

    public function testExecuteQuery()
    {
        global $sar;

        $query = $this->getMockForAbstractClass('\SimpleAR\Database\Query');
        $conn = $sar->db->connection();
        $conn->expects($this->once())->method('query')->with('SELECT * FROM articles', array());

        $query->executeQuery($conn, 'SELECT * FROM articles', array());
    }

    public function testRoot()
    {
        $query = $this->getMockForAbstractClass('\SimpleAR\Database\Query', array(), '', true, true, true, array('rootModel'));
        $query->expects($this->once())->method('rootModel')->with('Article');

        $query->root('Article');

        $query = $this->getMockForAbstractClass('\SimpleAR\Database\Query', array(), '', true, true, true, array('rootTable'));
        $query->expects($this->once())->method('rootTable')->with('t_user');

        $query->root('t_user');
    }

    public function testConstructorCallsRoot()
    {
        $class = '\SimpleAR\Database\Query';
        $query = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->setMethods(array('root'))
            ->getMockForAbstractClass();

        $query->expects($this->once())->method('root')->with('Article');

        $reflectedClass = new ReflectionClass($class);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($query, 'Article');
    }
}
