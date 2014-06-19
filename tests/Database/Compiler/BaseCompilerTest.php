<?php

use \SimpleAR\Database\Compiler\BaseCompiler;

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

        $query->from = 'articles';

        $expectedSql = 'DELETE FROM `articles`';
        $resultSql   = $compiler->compile($query);
    }

    public function testCompileDeleteUsingTableAlias()
    {
        $query = $this->getMock('\SimpleAR\Database\Query\Delete');
        $compiler = new BaseCompiler();

        $query->from = 'articles';
        $compiler->useTableAlias = true;

        $expectedSql = 'DELETE `articles` FROM `articles` `articles`';
        $resultSql   = $compiler->compile($query);

        $this->assertEquals($expectedSql, $resultSql);
    }
}
