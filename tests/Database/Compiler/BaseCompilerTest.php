<?php

use SimpleAR\Database\Compiler\BaseCompiler;
use SimpleAR\Database\Expression;
use SimpleAR\Database\JoinClause;
use SimpleAR\Database\Query;

use SimpleAR\Facades\DB;

class BaseCompilerTest extends PHPUnit_Framework_TestCase
{
    public function testCompileInsertWithAllComponents()
    {
        $compiler = new BaseCompiler();

        $components['into'] = 'articles';
        $components['insertColumns'] = array('blog_id', 'title');
        $components['values']  = array(12, 'Awesome!');

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?)';
        $resultSql = $compiler->compileInsert($components);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileInsertValuesWithArrayOfTuples()
    {
        $compiler = new BaseCompiler();

        $components['into'] = 'articles';
        $components['insertColumns'] = array('blog_id', 'title');
        $components['values']  = array(array(12, 'Awesome!'), array(1, 'Cool'), array(5, 'Yes'));

        $expectedSql = 'INSERT INTO `articles` (`blog_id`,`title`) VALUES(?,?),(?,?),(?,?)';
        $resultSql = $compiler->compileInsert($components);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testSelectBasic()
    {
        $compiler = new BaseCompiler();
        $components['from'] = array(new JoinClause('articles'));

        // "*" symbol for all columns.
        $cols = array('a' => array('columns' => array('*')));
        $components['columns'] = $cols;
        $expected = 'SELECT * FROM `articles`';
        $result = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);

        // For one particular column.
        $cols = array('a' => array('columns' => array('id')));
        $components['columns'] = $cols;
        $expected = 'SELECT `id` FROM `articles`';
        $result = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);

        // Several columns.
        $cols = ['a' => ['columns' => ['id', 'authorId' => 'author_id', 'title']]];
        $components['columns'] = $cols;
        $expected = 'SELECT `id`,`author_id` AS `authorId`,`title` FROM `articles`';
        $result   = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileProjection()
    {
        $c = new BaseCompiler;

        $this->assertEquals('`author_id`', $c->project(['author_id']));
        $this->assertEquals('`a`,`b`,`c`', $c->project(['a', 'b', 'c']));
        $this->assertEquals('`t1`.`col1`', $c->project(['col1'], 't1'));
        $this->assertEquals('`t1`.`col1`,`t1`.`col2`', $c->project(['col1', 'col2'], 't1'));
        $this->assertEquals('`t1`.`col1` AS `_.attr1`', $c->project(['attr1' => 'col1'], 't1', '_'));
        $this->assertEquals('`t1`.`col1` AS `_.attr1`,`t1`.`col2` AS `_.attr2`', $c->project(['attr1' => 'col1', 'attr2' => 'col2'], 't1', '_'));
    }

    public function testSelectBasicWithColumnsAndColumn()
    {
        $compiler = new BaseCompiler();
        $components['from'] = array(new JoinClause('articles'));
        $expr = new Expression('AVG(created_at) AS average');
        $components['columns'] = [
            '_' => ['columns' => ['id', 'author_id'], 'resultAlias' => ''],
            ['column' => $expr, 'alias' => ''],
        ];

        $expected = 'SELECT `id`,`author_id`,AVG(created_at) AS average FROM `articles`';
        $result   = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);
    }

    public function notestSelectBasicWithTableAlias()
    {
        $compiler = new BaseCompiler();
        //$compiler->useTableAlias = true;

        $components['from'] = array(new JoinClause('articles', 'a'));

        // "*" symbol for all columns.
        $cols = array('a' => array('columns' => array('*'), 'resultAlias' => ''));
        $components['columns'] = $cols;
        $expected = 'SELECT `a`.* FROM `articles` `a`';
        $result   = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);

        // Several columns.
        $cols = array('a' => array(
            'columns' => array('id', 'author_id' => 'authorId', 'title'),
            'resultAlias' => '_',
        ));
        $components['columns'] = $cols;
        $expected = 'SELECT `a`.`id`,`a`.`author_id` AS `authorId`,`a`.`title` FROM `articles` `a`';
        $result   = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);
    }

    public function testSelectBasicWithResultAlias()
    {
        $compiler = new BaseCompiler();
        //$compiler->useTableAlias = true;
        $compiler->useResultAlias = true;

        $components['from'] = array(new JoinClause('articles', 'a'));

        // Several columns.
        $cols = ['a' => [
            'columns' => ['id', 'authorId' => 'author_id', 'title'],
            'resultAlias' => '_'
        ]];
        $components['columns'] = $cols;
        $expected = 'SELECT `id` AS `_.id`,`author_id` AS `_.authorId`,`title` AS `_.title` FROM `articles`';
        $result   = $compiler->compileSelect($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileDelete()
    {
        $compiler = new BaseCompiler();

        $components['deleteFrom'] = 'articles'; 
        $expectedSql = 'DELETE FROM `articles`';
        $resultSql   = $compiler->compileDelete($components);

        $query = new Query();

        $components['deleteFrom'] = 'articles';
        $components['using'] = array(new JoinClause('articles'));
        //$compiler->useTableAlias = true;

        $expectedSql = 'DELETE FROM `articles` USING `articles` `articles`';
        $resultSql   = $compiler->compileDelete($components);

        $this->assertEquals($expectedSql, $resultSql);

        $query = new Query();

        // Without ON clause.
        $components['deleteFrom'] = array('articles', 'users');
        $components['using'] = array(new JoinClause('articles'), new JoinClause('users'));

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` `articles` INNER JOIN `users` `users`';
        $resultSql   = $compiler->compileDelete($components);

        // With ON clause.
        $components['deleteFrom'] = array('articles', 'users');
        $joins[] = new JoinClause('articles');
        $joins[] = (new JoinClause('users'))->on('articles', 'user_id', 'users', 'id');
        $components['using'] = $joins;

        $expectedSql = 'DELETE FROM `articles`,`users` USING `articles` `articles` INNER JOIN `users` `users` ON `articles`.`user_id` = `users`.`id`';
        $resultSql   = $compiler->compileDelete($components);

        $this->assertEquals($expectedSql, $resultSql);
    }

    public function testCompileDeleteOnSeveralTablesWithAlias()
    {
        $compiler = new BaseCompiler();
        //$compiler->useTableAlias = true;

        // Without ON clause.
        $components['deleteFrom'] = array('a', 'u');
        $components['using'] = array(new JoinClause('articles', 'a'), new JoinClause('users', 'u'));

        $expected = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u`';
        $result   = $compiler->compileDelete($components);

        $this->assertEquals($expected, $result);

        // With ON clause.
        $components['deleteFrom'] = array('a', 'u');
        $joins[] = new JoinClause('articles', 'a');
        $joins[] = (new JoinClause('users', 'u'))->on('a', 'user_id', 'u', 'id');
        $components['using'] = $joins;

        $expected = 'DELETE FROM `a`,`u` USING `articles` `a` INNER JOIN `users` `u` ON `a`.`user_id` = `u`.`id`';
        $result   = $compiler->compileDelete($components);

        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereBasic()
    {
        $compiler = new BaseCompiler();

        $where = ['type' => 'Basic', 'table' => 'a', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND'];
        //$where = new SimpleCond('a', 'author_id', '=', 12);
        $components['where'] = array($where);

        $expected = 'WHERE `author_id` = ?';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereBasics()
    {
        $compiler = new BaseCompiler();

        $where[] = ['type' => 'Basic', 'table' => 'a', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND'];
        //$where[] = new SimpleCond('a', 'author_id', '=', 12);
        $where[] = ['type' => 'Basic', 'table' => 'a', 'cols' => ['title'], 'op' => '=', 'val' => 'Das Kapital', 'logic' => 'AND'];
        //$where[] = new SimpleCond('a', 'title', '=' , 'Das Kapital');
        $components['where'] = $where;

        $expected = 'WHERE `author_id` = ? AND `title` = ?';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);

        $w[] = ['type' => 'Basic', 'table' => 'a', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND'];
        //$w[] = new SimpleCond('a', 'author_id', '=', 12);
        $w[] = ['type' => 'Basic', 'table' => 'a', 'cols' => ['author_id'], 'op' => '=', 'val' => 99, 'logic' => 'OR'];
        //$w[] = new SimpleCond('a', 'author_id', '=' , 99, 'OR');
        $w[] = ['type' => 'Basic', 'table' => 'a', 'cols' => ['author_id'], 'op' => '=', 'val' => 1, 'logic' => 'OR'];
        //$w[] = new SimpleCond('a', 'author_id', '=' , 1, 'OR');
        $components['where'] = $w;
        $expected = 'WHERE `author_id` = ? OR `author_id` = ? OR `author_id` = ?';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);

        $w = ['type' => 'In', 'table' => 'a', 'cols' => ['author_id'], 'val' => [12, 99, 1], 'logic' => 'AND'];
        //$where = new SimpleCond('a', 'author_id', 'IN', array(12, 99, 1));
        $components['where'] = array($w);
        $expected = 'WHERE `author_id` IN (?,?,?)';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    /**
     * @covers ::_whereExists
     */
    public function testCompileWhereExists()
    {
        $compiler = new BaseCompiler();

        $select = new Query();
        $select->setComponent('columns', array('a' => array('columns' => array('id'))));
        $select->setComponent('from', array(new JoinClause('articles')));

        $where = ['type' => 'Exists', 'query' => $select, 'logic' => 'AND'];
        //$where = new ExistsCond($select);
        $components['where'] = array($where);

        $expected = 'WHERE EXISTS (SELECT `id` FROM `articles`)';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    /**
     * @covers ::_whereNotExists
     */
    public function testCompileWhereNotExists()
    {
        $compiler = new BaseCompiler();

        $select = new Query();
        $select->setComponent('columns', ['a' => ['columns' => ['id']]]);
        $select->setComponent('from', [new JoinClause('articles')]);

        $where = ['type' => 'Exists', 'query' => $select, 'logic' => 'AND', 'not' => true];
        //$where = new ExistsCond($select);
        $components['where'] = [$where];

        $expected = 'WHERE NOT EXISTS (SELECT `id` FROM `articles`)';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileWhereAttribute()
    {
        $compiler = new BaseCompiler();

        $w = ['type' => 'Attribute', 'lTable' => 'a', 'lCols' => ['author_id'], 'op' => '=', 'rTable' => 'b', 'rCols' => ['id'], 'logic' => 'AND'];
        //$where = new AttrCond('a', 'author_id', '=', 'b', 'id');
        $components['where'] = array($w);

        $expected = 'WHERE `author_id` = `id`';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);

        $compiler->useTableAlias = true;
        $expected = 'WHERE `a`.`author_id` = `b`.`id`';
        $result   = $compiler->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileIsNull()
    {
        $c = new BaseCompiler();

        $where = ['type' => 'Null', 'table' => '', 'cols' => ['author_id'], 'val' => null, 'logic' => 'AND', 'not' => false];
        //$where = new SimpleCond('a', 'author_id', '=', 12);
        $components['where'] = array($where);

        $expected = 'WHERE `author_id` IS NULL';
        $result   = $c->compileWhere($components);
        $this->assertEquals($expected, $result);

        $where = ['type' => 'Null', 'table' => '', 'cols' => ['author_id'], 'val' => null, 'logic' => 'AND', 'not' => true];
        //$where = new SimpleCond('a', 'author_id', '=', 12);
        //$where = new SimpleCond('a', 'author_id', '=', 12);
        $components['where'] = array($where);

        $expected = 'WHERE `author_id` IS NOT NULL';
        $result   = $c->compileWhere($components);
        $this->assertEquals($expected, $result);
    }

    public function testCompileAggregates()
    {
        $c = new BaseCompiler();

        $agg[] = array('columns' => array('*'), 'function' => 'COUNT', 'tableAlias' => '', 'resultAlias' => '');
        $agg[] = array('columns' => array('*'), 'function' => 'COUNT', 'tableAlias' => 'articles', 'resultAlias' => '#articles');
        $agg[] = array('columns' => array('views'), 'function' => 'SUM', 'tableAlias' => 'articles', 'resultAlias' => '#views');

        $components['from'] = array(new JoinClause('articles'));
        $components['aggregates'] = $agg;

        $expected = 'SELECT COUNT(*),COUNT(`articles`.*) AS `#articles`,SUM(`articles`.`views`) AS `#views` FROM `articles`';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testCompileColumnsAndAggregates()
    {
        $c = new BaseCompiler();

        $agg[] = array('columns' => array('views'), 'function' => 'SUM', 'tableAlias' => 'articles', 'resultAlias' => '#views');

        $components['from'] = [new JoinClause('articles')];
        $components['aggregates'] = $agg;
        $components['columns'] = ['' => ['columns' => ['*']]];

        $expected = 'SELECT * ,SUM(`articles`.`views`) AS `#views` FROM `articles`';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testCompileLimit()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));
        $components['limit'] = 5;

        $expected = 'SELECT * FROM `articles` LIMIT 5';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testOffset()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));
        $components['offset'] = 12;

        $expected = 'SELECT * FROM `articles` OFFSET 12';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testOrderBy()
    {
        // No JOIN, No use of table alias.
        $c = new BaseCompiler();
        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = [new JoinClause('articles', '_')];
        $components['orderBy'] = array(
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
                'sort' => 'DESC',
            ),
        );
        $expected = 'SELECT * FROM `articles` ORDER BY `created_at` DESC';
        $this->assertEquals($expected, $c->compileSelect($components));

        // With JOIN.
        $c = new BaseCompiler();
        $components['columns'] = array('' => array('columns' => array('*')));
        $jc[] = new JoinClause('articles', '_');
        $jc[] = (new JoinClause('authors', 'author'))->on('_', 'author_id', 'author', 'id');
        $components['from'] = $jc;
        $components['orderBy'] = array(
            array(
                'tableAlias' => 'author',
                'column' => 'last_name',
                'sort' => 'ASC',
            ),
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
                'sort' => 'DESC',
            ),
        );

        $expected = 'SELECT * FROM `articles` `_` INNER JOIN `authors` `author` ON `_`.`author_id` = `author`.`id` ' .
            'ORDER BY `author`.`last_name` ASC,`_`.`created_at` DESC';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testGroupBy()
    {
        // No JOIN.
        $c = new BaseCompiler();
        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = [new JoinClause('articles', '_')];
        $components['groupBy'] = array(
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
            ),
        );

        $expected = 'SELECT * FROM `articles` GROUP BY `created_at`';
        $this->assertEquals($expected, $c->compileSelect($components));

        // With JOIN
        $c = new BaseCompiler();
        $components['columns'] = array('' => array('columns' => array('*')));
        $jc[] = new JoinClause('articles', '_');
        $jc[] = (new JoinClause('authors', 'author'))->on('_', 'author_id', 'author', 'id');
        $components['from'] = $jc;
        $components['groupBy'] = array(
            array(
                'tableAlias' => 'author',
                'column' => 'last_name',
            ),
            array(
                'tableAlias' => '_',
                'column' => 'created_at',
            ),
        );

        $expected = 'SELECT * FROM `articles` `_` INNER JOIN `authors` `author` ON `_`.`author_id` = `author`.`id` ' .
            'GROUP BY `author`.`last_name`,`_`.`created_at`';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testWith()
    {
        $jc = array(
            (new JoinClause('blogs', '_')),
            (new JoinClause('articles', 'articles', JoinClause::LEFT))->on('_', 'id', 'articles', 'blog_id'),
            (new JoinClause('authors', 'articles.author', JoinClause::LEFT))->on('articles', 'author_id', 'articles.author', 'id')
        );

        $columns = array(
            '_' => ['columns' => ['*'], 'resultAlias' => ''],
            'articles' => ['columns' => ['*'], 'resultAlias' => ''],
            'articles.author' => ['columns' => ['firstName' => 'first_name', 'lastName' => 'last_name'], 'resultAlias' => 'articles.author'],
        );

        $components['from'] = $jc;
        $components['columns'] = $columns;

        $c = new BaseCompiler();

        $sql = 'SELECT `_`.*,`articles`.*,`articles.author`.`first_name` AS `articles.author.firstName`,`articles.author`.`last_name` AS `articles.author.lastName`'
            . ' FROM `blogs` `_`' 
            . ' LEFT JOIN `articles` `articles` ON `_`.`id` = `articles`.`blog_id`'
            . ' LEFT JOIN `authors` `articles.author` ON `articles`.`author_id` = `articles.author`.`id`';
        $val = array();

        $this->assertEquals($sql, $c->compileSelect($components));
    }

    public function testIn()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));

        $w = ['type' => 'In', 'table' => '', 'cols' => ['author_id'], 'val' => [1, 2], 'logic' => 'AND'];
        //$where = new InCond('', 'author_id', array(1, 2));
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE `author_id` IN (?,?)';
        $this->assertEquals($expected, $c->compileSelect($components));

        $w['not'] = true;
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE `author_id` NOT IN (?,?)';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testInWithEmptyArray()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));

        $w = ['type' => 'In', 'table' => '', 'cols' => ['author_id'], 'val' => [], 'logic' => 'AND'];
        //$where = new InCond('', 'author_id', array());
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE FALSE';
        $this->assertEquals($expected, $c->compileSelect($components));

        $w['not'] = true;
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE TRUE';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testUpdate()
    {
        $c = new BaseCompiler();
        $components['updateFrom'] = [new JoinClause('articles', '_')];
        $components['set'] = [[
            'tableAlias' => '_',
            'column' => 'title',
            'value' => 'Yo',
        ]];

        $sql = 'UPDATE `articles` SET `title` = ?';
        $this->assertEquals($sql, $c->compileUpdate($components));
    }

    public function testMultiColumnsCondition()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));

        $w = ['type' => 'In', 'table' => '',
            'cols' => ['author_id','blog_id'],
            'val' => [[1, 2], [2,3], [3,3]],
            'logic' => 'AND'];

        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE (`author_id`,`blog_id`) IN ((?,?),(?,?),(?,?))';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testWhereRaw()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));

        $w = ['type' => 'Raw', 
            'val' => new Expression("FN(attr) = 'val'"),
            'logic' => 'AND',
        ];

        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE FN(attr) = \'val\'';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testWhereTuple()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = array(new JoinClause('articles'));

        $w = ['type' => 'Tuple', 'table' => '',
            'cols' => ['author_id', 'blog_id'],
            'val' => [[1,2], [1,3], [2,2]],
            'logic' => 'AND'
        ];
        //$where = new InCond('', 'author_id', array(1, 2));
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE (`author_id`,`blog_id`) IN ((?,?),(?,?),(?,?))';
        $this->assertEquals($expected, $c->compileSelect($components));

        $w['not'] = true;
        $components['where'] = array($w);
        $expected = 'SELECT * FROM `articles` WHERE (`author_id`,`blog_id`) NOT IN ((?,?),(?,?),(?,?))';
        $this->assertEquals($expected, $c->compileSelect($components));
    }

    public function testWhereTupleOnDifferentTables()
    {
        $c = new BaseCompiler();

        $components['columns'] = array('' => array('columns' => array('*')));
        $components['from'] = [
            (new JoinClause('articles', '_')),
            (new JoinClause('blogs', 'blog'))->on('_', 'blog_id', 'blog', 'id'),
        ];
        $components['where'] = [
            [
                'type' => 'Tuple', 'table' => ['_', 'blog'],
                'cols' => ['author_id', 'id'],
                'val' => [[1,2], [1,3], [2,2]],
                'logic' => 'AND', 'not' => false
            ],
        ];
        $expected = 'SELECT * FROM `articles` `_` INNER JOIN `blogs` `blog` ON `_`.`blog_id` = `blog`.`id` WHERE (`_`.`author_id`,`blog`.`id`) IN ((?,?),(?,?),(?,?))';
        $this->assertEquals($expected, $c->compileSelect($components));
    }
}
