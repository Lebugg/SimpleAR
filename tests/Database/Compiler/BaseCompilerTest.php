<?php

use \SimpleAR\Database\Query;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\JoinClause;

use \SimpleAR\Database\Condition\Simple as SimpleCond;
use \SimpleAR\Database\Condition\Nested as NestedCond;
use \SimpleAR\Database\Condition\Exists as ExistsCond;

class BaseCompilerTest extends PHPUnit_Framework_TestCase
{
    public function testCompileInsertWithAllComponents()
    {
        $query    = new Query();
        $compiler = new BaseCompiler();

        $query->components['into'] = 'articles';
        $query->components['insertColumns'] = array('blog_id', 'title');
        $query->components['values']  = array(12, 'Awesome!');

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?)';
        $resultSql   = $compiler->compileInsert($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileInsertValuesWithArrayOfTuples()
    {
        $query = new Query();
        $compiler = new BaseCompiler();

        $query->components['into'] = 'articles';
        $query->components['insertColumns'] = array('blog_id', 'title');
        $query->components['values']  = array(array(12, 'Awesome!'), array(1, 'Cool'), array(5, 'Yes'));

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?),(?,?),(?,?)';
        $resultSql   = $compiler->compileInsert($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testSelectBasic()
    {
        $query = new Query();
        $compiler = new BaseCompiler();

        $query->components['from'] = array(new JoinClause('articles'));

        // "*" symbol for all columns.
        $cols = array('a' => array('columns' => array('*')));
        $query->components['columns'] = $cols;
        $expected = 'SELECT * FROM `articles`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);

        // For one particular column.
        $cols = array('a' => array('columns' => array('id')));
        $query->components['columns'] = $cols;
        $expected = 'SELECT `id` FROM `articles`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);

        // Several columns.
        $cols = array('a' => array('columns' => array('id', 'author_id' => 'authorId', 'title')));
        $query->components['columns'] = $cols;
        $expected = 'SELECT `id`,`author_id` AS `authorId`,`title` FROM `articles`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);
    }

    public function testSelectBasicWithTableAlias()
    {
        $query = new Query();
        $compiler = new BaseCompiler();
        $compiler->useTableAlias = true;

        $query->components['from'] = array(new JoinClause('articles', 'a'));

        // "*" symbol for all columns.
        $cols = array('a' => array('columns' => array('*'), 'resultAlias' => ''));
        $query->components['columns'] = $cols;
        $expected = 'SELECT `a`.* FROM `articles` `a`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);

        // Several columns.
        $cols = array('a' => array(
            'columns' => array('id', 'author_id' => 'authorId', 'title'),
            'resultAlias' => '_',
        ));
        $query->components['columns'] = $cols;
        $expected = 'SELECT `a`.`id`,`a`.`author_id` AS `authorId`,`a`.`title` FROM `articles` `a`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);
    }

    public function testSelectBasicWithResultAlias()
    {
        $query = new Query();
        $compiler = new BaseCompiler();
        $compiler->useTableAlias = true;
        $compiler->useResultAlias = true;

        $query->components['from'] = array(new JoinClause('articles', 'a'));

        // Several columns.
        $cols = array('a' => array(
            'columns' => array('id', 'author_id' => 'authorId', 'title'),
            'resultAlias' => '_'
        ));
        $query->components['columns'] = $cols;
        $expected = 'SELECT `a`.`id` AS `_.id`,`a`.`author_id` AS `_.authorId`,`a`.`title` AS `_.title` FROM `articles` `a`';
        $result   = $compiler->compileSelect($query);
        $this->assertEquals($expected, $result);
    }
    public function testCompileDelete()
    {
        $query = new Query();
        $compiler = new BaseCompiler();

        $query->components['deleteFrom'] = 'articles'; 
        $expectedSql = 'DELETE FROM `articles`';
        $resultSql   = $compiler->compileDelete($query);
    }

    public function testCompileDeleteUsingTableAlias()
    {
        $query = new Query();
        $compiler = new BaseCompiler();

        $query->components['deleteFrom'] = 'articles';
        $query->components['using'] = array(new JoinClause('articles'));
        $compiler->useTableAlias = true;

        $expectedSql = 'DELETE FROM `articles` USING `articles` `articles`';
        $resultSql   = $compiler->compileDelete($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDeleteOnSeveralTablesWithoutAlias()
    {
        $query = new Query();
        $compiler = new BaseCompiler();

        // Without ON clause.
        $query->components['deleteFrom'] = array('articles', 'users');
        $query->components['using'] = array(new JoinClause('articles'), new JoinClause('users'));

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` INNER JOIN `users`';
        $resultSql   = $compiler->compileDelete($query);

        // With ON clause.
        $query->components['deleteFrom'] = array('articles', 'users');
        $joins[] = new JoinClause('articles');
        $joins[] = (new JoinClause('users'))->on('articles', 'user_id', 'users', 'id');
        $query->components['using'] = $joins;

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` INNER JOIN `users` ON `articles`.`user_id` = `users`.`id`';
        $resultSql   = $compiler->compileDelete($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDeleteOnSeveralTablesWithAlias()
    {
        $query    = new Query();
        $compiler = new BaseCompiler();
        $compiler->useTableAlias = true;

        // Without ON clause.
        $query->components['deleteFrom'] = array('a', 'u');
        $query->components['using'] = array(new JoinClause('articles', 'a'), new JoinClause('users', 'u'));

        $expected = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u`';
        $result   = $compiler->compileDelete($query);

        $this->assertEquals($expected, $result);

        // With ON clause.
        $query->components['deleteFrom'] = array('a', 'u');
        $joins[] = new JoinClause('articles', 'a');
        $joins[] = (new JoinClause('users', 'u'))->on('a', 'user_id', 'u', 'id');
        $query->components['using'] = $joins;

        $expected = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u` ON `a`.`user_id` = `u`.`id`';
        $result   = $compiler->compileDelete($query);

        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereBasic()
    {
        $query    = new Query();
        $compiler = new BaseCompiler();

        $where = new SimpleCond('a', 'author_id', '=', 12);
        $query->components['where'] = array($where);

        $expected = 'WHERE `author_id` = ?';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);

        $compiler->useTableAlias = true;
        $expected = 'WHERE `a`.`author_id` = ?';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereBasics()
    {
        $query    = new Query();
        $compiler = new BaseCompiler();

        $wheres[] = new SimpleCond('a', 'author_id', '=', 12);
        $wheres[] = new SimpleCond('a', 'title', '=' , 'Das Kapital');
        $query->components['where'] = $wheres;

        $expected = 'WHERE `author_id` = ? AND `title` = ?';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);

        $wheres = array();
        $wheres[] = new SimpleCond('a', 'author_id', '=', 12);
        $wheres[] = new SimpleCond('a', 'author_id', '=' , 99, 'OR');
        $wheres[] = new SimpleCond('a', 'author_id', '=' , 1, 'OR');
        $query->components['where'] = $wheres;
        $expected = 'WHERE `author_id` = ? OR `author_id` = ? OR `author_id` = ?';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);

        $where = new SimpleCond('a', 'author_id', 'IN', array(12, 99, 1));
        $query->components['where'] = array($where);
        $expected = 'WHERE `author_id` IN (?,?,?)';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereExists()
    {
        $query    = new Query();
        $compiler = new BaseCompiler();

        $select = new Query();
        $select->components['columns'] = array('a' => array('columns' => array('id')));
        $select->components['from'] = array(new JoinClause('articles'));

        $where = new ExistsCond($select);
        $query->components['where'] = array($where);

        $expected = 'WHERE EXISTS (SELECT `id` FROM `articles`)';
        $result   = $compiler->compileWhere($query);
        $this->assertEquals($expected, $result);
    }

    public function testCompileAggregates()
    {
        $q = new Query();
        $c = new BaseCompiler();

        $agg[] = array('columns' => array('*'), 'function' => 'COUNT', 'tableAlias' => '', 'resultAlias' => '');
        $agg[] = array('columns' => array('*'), 'function' => 'COUNT', 'tableAlias' => 'articles', 'resultAlias' => '#articles');
        $agg[] = array('columns' => array('views'), 'function' => 'SUM', 'tableAlias' => 'articles', 'resultAlias' => '#views');

        $q->components['from'] = array(new JoinClause('articles'));
        $q->components['aggregates'] = $agg;

        $expected = 'SELECT COUNT(*),COUNT(`articles`.*) AS `#articles`,SUM(`articles`.`views`) AS `#views` FROM `articles`';
        $this->assertEquals($expected, $c->compileSelect($q));
    }
}
