<?php

use \Mockery as m;

use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Builder\InsertBuilder;
use \SimpleAR\Database\Builder\DeleteBuilder;
use \SimpleAR\Database\Builder\UpdateBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Connection;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\Query;

/**
 * @coversDefaultClass \SimpleAR\Database\Query
 */
class QueryTest extends PHPUnit_Framework_TestCase
{
    public function testGetConnection()
    {
        $conn = new Connection;
        $q = new Query(null, $conn);
        $this->assertEquals($conn, $q->getConnection());
    }

    public function testRunProcess()
    {
        $b = $this->getMock('\SimpleAR\Database\Builder', ['build', 'getValues']);
        $b->type = 'Select';
        $c = $this->getMockForAbstractClass('\SimpleAR\Database\Compiler', [], '', false, false,false,['compile']);
        $conn  = $this->getMock('\SimpleAR\Database\Connection', ['select', 'getCompiler']);

        $b->expects($this->once())->method('build')->will($this->returnValue([]));
        $b->expects($this->once())->method('getValues')->will($this->returnValue([]));
        $c->expects($this->once())->method('compile')->will($this->returnValue(['SQL', []]));
        $conn->expects($this->once())->method('select');

        $q = new Query($b, $conn);
        $q->setCompiler($c);
        $q->run();
    }

    public function testQueryIsSafe()
    {
        $q = new Query;

        $sql = 'DELETE FROM users';
        $this->assertFalse($q->queryIsSafe($sql));

        $sql = 'DELETE FROM users WHERE id = 3';
        $this->assertTrue($q->queryIsSafe($sql));
    }

    public function testExecuteQuery()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['query']);
        $conn->expects($this->once())->method('query')->with('SELECT * FROM articles', []);

        $q = new Query(null, $conn);
        $q->executeQuery('SELECT * FROM articles', []);
    }

    public function testSelectQuery()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['select']);

        $q = new Query(new SelectBuilder(), $conn);
        $q->setCompiler(new BaseCompiler);
        $q->root('Article')
            ->conditions([
                'authorId' => 12,
                'blogId' => 1,
                [
                    'title' => array('Das Kapital', 'Essays'),
                    'OR', 'authorId' => 1,
                ],
            ])
            ->select(['*']);

        $sql = 'SELECT `blog_id` AS `blogId`,`author_id` AS `authorId`,`title` AS `title`,`created_at` AS `created_at`,`views` AS `views`,`id` AS `id` FROM `articles` WHERE `author_id` = ? AND `blog_id` = ? AND (`title` IN (?,?) OR `author_id` = ?)';
        $val = [12, 1, 'Das Kapital', 'Essays', 1];
        $conn->expects($this->once())->method('select')->with($sql, $val);

        $q->run();
    }

    public function testInsertQuery()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['insert']);

        $q = new Query(new InsertBuilder(), $conn);
        $q->setCompiler(new BaseCompiler);
        $q->root('Article')
            ->fields(['blogId', 'title', 'authorId'])
            ->values([
                [1, 'A', 12],
                [1, 'B', 15],
                [3, 'C', 16],
            ]);

        $sql = 'INSERT INTO `articles` (`blog_id`,`title`,`author_id`) VALUES(?,?,?),(?,?,?),(?,?,?)';
        $val = [1, 'A', 12, 1, 'B', 15, 3, 'C', 16];
        $conn->expects($this->once())->method('insert')
            ->with($sql, $val);

        $q->run();
    }

    public function testUpdateQuery()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['update']);

        $q = new Query(new UpdateBuilder(), $conn);
        $q->setCompiler(new BaseCompiler);
        $q->root('Author')
            ->conditions([
                ['id', '!=', 12],
                'lastName' => 'Bar',
            ])
            ->set('firstName', 'Foo');

        $sql = 'UPDATE `authors` SET `first_name` = ? WHERE `id` != ? AND `last_name` = ?';
        $val = ['Foo', 12, 'Bar'];
        $conn->expects($this->once())->method('update')
            ->with($sql, $val);

        $q->run();
    }

    public function testDeleteQuery()
    {
        $conn = $this->getMock('\SimpleAR\Database\Connection', ['delete']);

        $q = new Query(new DeleteBuilder(), $conn);
        $q->setCompiler(new BaseCompiler);
        $q->root('Article')
            ->conditions([
                ['authorId', '!=', 12],
                ['blogId', '!=', [1, 3]],
            ]);

        $sql = 'DELETE FROM `articles` WHERE `author_id` != ? AND `blog_id` NOT IN (?,?)';
        $val = [12, 1, 3];
        $conn->expects($this->once())->method('delete')
            ->with($sql, $val);

        $q->run();
    }

    public function testFalsyConditionValues()
    {
        $conn = $this->getMock('SimpleAR\Database\Connection', ['select']);
        $q = new Query(new SelectBuilder(), $conn);
        $q->setCompiler(new BaseCompiler);
        $q->root('Author')
            ->select(['*'], false)
            ->conditions([
                'age' => 0
            ]);

        $sql = 'SELECT * FROM `authors` WHERE `age` = ?';
        $val = [0];
        $conn->expects($this->once())->method('select')
            ->with($sql, $val);

        $q->run();
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

        $val = [1, 2, [3], [new Expression(''), 3, [4, new Expression('')]]];
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

    /**
     * @covers ::lastInsertId()
     */
    public function testLastInsertId()
    {
        $conn = m::mock('\SimpleAR\Database\Connection[insert]');
        $conn->shouldReceive('insert')->once()->andReturn(12);

        $q = new Query(new InsertBuilder());
        $q->root('Article')->fields(['title'])->values(['Yo.']);
        $q->setConnection($conn);
        $this->assertEquals(12, $q->lastInsertId());
    }
}
