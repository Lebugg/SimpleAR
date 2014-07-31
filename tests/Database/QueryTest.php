<?php

use \SimpleAR\Database\Query;
use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Builder\InsertBuilder;
use \SimpleAR\Database\Builder\DeleteBuilder;
use \SimpleAR\Database\Builder\UpdateBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Expression;
use \SimpleAR\Orm\Model;

class QueryTest extends PHPUnit_Framework_TestCase
{
    public function testGetConnection()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $stub = new Query(null, null, $conn);
        $this->assertEquals($conn, $stub->getConnection());
    }

    public function testRunProcess()
    {
        global $sar;

        $builder  = $this->getMock('\SimpleAR\Database\Builder');
        $compiler = $this->getMock('\SimpleAR\Database\Compiler');
        $conn     = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $query = new Query($builder, $compiler, $conn);

        $builder->expects($this->once())->method('build');
        $builder->expects($this->once())->method('getValues')->will($this->returnValue(array()));
        $compiler->expects($this->once())->method('compile')->will($this->returnValue(array('SQL', array())));
        $conn->expects($this->once())->method('query');

        $query->run();
    }

    public function testQueryIsSafe()
    {
        $query = new Query();

        $sql = 'DELETE FROM users';
        $this->assertFalse($query->queryIsSafe($sql));

        $sql = 'DELETE FROM users WHERE id = 3';
        $this->assertTrue($query->queryIsSafe($sql));
    }

    public function testExecuteQuery()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));
        $conn->expects($this->once())->method('query')->with('SELECT * FROM articles', array());

        $query = new Query(null, null, $conn);
        $query->executeQuery('SELECT * FROM articles', array());
    }

    public function testSelectQuery()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $query = new Query(new SelectBuilder(), new BaseCompiler(), $conn);
        $query->root('Article')
            ->conditions(array(
                'authorId' => 12,
                'blogId' => 1,
                array(
                    'title' => array('Das Kapital', 'Essays'),
                    'OR', 'authorId' => 1,
                ),
            ));

        $sql = 'SELECT `blog_id` AS `blogId`,`author_id` AS `authorId`,`title` AS `title`,`created_at` AS `created_at`,`id` AS `id` FROM `articles` WHERE `author_id` = ? AND `blog_id` = ? AND (`title` IN (?,?) OR `author_id` = ?)';
        $val = array(12, 1, 'Das Kapital', 'Essays', 1);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $query->run();
    }

    public function testInsertQuery()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $query = new Query(new InsertBuilder(), new BaseCompiler(), $conn);
        $query->root('Article')
            ->fields('blogId', 'title', 'authorId')
            ->values(array(
                array(1, 'A', 12),
                array(1, 'B', 15),
                array(3, 'C', 16),
            ));

        $sql = 'INSERT INTO `articles` (`blog_id`,`title`,`author_id`) VALUES(?,?,?),(?,?,?),(?,?,?)';
        $val = array(1, 'A', 12, 1, 'B', 15, 3, 'C', 16);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $query->run();
    }

    public function testUpdateQuery()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $query = new Query(new UpdateBuilder(), new BaseCompiler(), $conn);
        $query->root('Author')
            ->conditions(array(
                array('id', '!=', 12),
                'lastName' => 'Bar',
            ))
            ->set('firstName', 'Foo');

        $sql = 'UPDATE `authors` SET `first_name` = ? WHERE `id` != ? AND `last_name` = ?';
        $val = array('Foo', 12, 'Bar');
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $query->run();
    }

    public function testDeleteQuery()
    {
        global $sar;
        $conn = $this->getMock('\SimpleAR\Database\Connection', array(), array($sar->cfg));

        $query = new Query(new DeleteBuilder(), new BaseCompiler(), $conn);
        $query->root('Article')
            ->conditions(array(
                array('authorId', '!=', 12),
                array('blogId', '!=', array(1, 3)),
            ));

        $sql = 'DELETE FROM `articles` WHERE `author_id` != ? AND `blog_id` NOT IN (?,?)';
        $val = array(12, 1, 3);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $query->run();
    }

    public function testFalsyConditionValues()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection');
        $query = new Query(new SelectBuilder(), new BaseCompiler(), $conn);
        $query->root('Author')
            ->select(array('*'), false)
            ->conditions(array(
                'age' => 0
            ));

        $sql = 'SELECT * FROM `authors` WHERE `age` = ?';
        $val = array(0);
        $conn->expects($this->once())->method('query')->with($sql, $val);

        $query->run();
    }

    public function testPrepareValuesForExecution()
    {
        $q = new Query;

        $val = [[1, 2], 2, [[3, 4, 5]]];
        $exp = [1, 2, 2, 3, 4, 5];
        $this->assertEquals($exp, $q->prepareValuesForExecution($val));

        $val = [[[1], 3]];
        $exp = [1, 3];
        $this->assertEquals($exp, $q->prepareValuesForExecution($val));

        $val = [1, 2, [3], [new Expression, 3, [4, new Expression]]];
        $exp = [1, 2, 3, 3, 4];
        $this->assertEquals($exp, $q->prepareValuesForExecution($val));
    }

    public function testPrepareValuesForExecutionWithModels()
    {
        $q = new Query;

        $m1 = new Article;
        $m1->id = 2;
        $m2 = new Article;
        $m2->id = [4, 5];

        $val = [$m1, [2, [$m2]], 6];
        $exp = [2, 2, 4, 5, 6];
        $this->assertEquals($exp, $q->prepareValuesForExecution($val));
    }

    public function testCallsToBuilder()
    {
        $b = $this->getMock('SimpleAR\Database\Builder\SelectBuilder', array('select'));
        $b->expects($this->exactly(1))->method('select')->with(['title', 'authorId', 'created_at']);

        $q = new Query();
        $q->setBuilder($b);
        $q->select(['title', 'authorId', 'created_at']);
    }
}
