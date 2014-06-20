<?php

use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\JoinClause;

class BaseCompilerTest extends PHPUnit_Framework_TestCase
{
    public function testCompileInsertWithAllComponents()
    {
        $query    = $this->getMock('\SimpleAR\Database\Query\Insert');
        $compiler = new BaseCompiler();

        $query->into = 'articles';
        $query->columns = array('blog_id', 'title');
        $query->values  = array(12, 'Awesome!');

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?)';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileInsertValuesWithArrayOfTuples()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Insert');
        $compiler = new BaseCompiler();

        $query->into = 'articles';
        $query->columns = array('blog_id', 'title');
        $query->values  = array(array(12, 'Awesome!'), array(1, 'Cool'), array(5, 'Yes'));

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?),(?,?),(?,?)';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDelete()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Delete');
        $compiler = new BaseCompiler();

        $query->deleteFrom = 'articles'; 
        $expectedSql = 'DELETE FROM `articles`';
        $resultSql   = $compiler->compile($query);
    }

    public function testCompileDeleteUsingTableAlias()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Delete');
        $compiler = new BaseCompiler();

        $query->deleteFrom = 'articles';
        $query->using = array(new JoinClause('articles'));
        $compiler->useTableAlias = true;

        $expectedSql = 'DELETE FROM `articles` USING `articles` `articles`';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDeleteOnSeveralTablesWithoutAlias()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Delete');
        $compiler = new BaseCompiler();

        // Without ON clause.
        $query->deleteFrom = array('articles', 'users');
        $query->using = array(new JoinClause('articles'), new JoinClause('users'));

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` INNER JOIN `users`';
        $resultSql   = $compiler->compile($query);

        // With ON clause.
        $query->deleteFrom = array('articles', 'users');
        $joins[] = new JoinClause('articles');
        $joins[] = (new JoinClause('users'))->on('articles', 'user_id', 'users', 'id');
        $query->using = $joins;

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` INNER JOIN `users` ON `articles`.`user_id` = `users`.`id`';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDeleteOnSeveralTablesWithAlias()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Delete');
        $compiler = new BaseCompiler();
        $compiler->useTableAlias = true;

        // Without ON clause.
        $query->deleteFrom = array('a', 'u');
        $query->using = array(new JoinClause('articles', 'a'), new JoinClause('users', 'u'));

        $expectedSql = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u`';
        $resultSql   = $compiler->compile($query);

        // With ON clause.
        $query->deleteFrom = array('a', 'u');
        $joins[] = new JoinClause('articles', 'a');
        $joins[] = (new JoinClause('users', 'u'))->on('a', 'user_id', 'u', 'id');
        $query->using = $joins;

        $expectedSql = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u` ON `a`.`user_id` = `u`.`id`';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }
}
