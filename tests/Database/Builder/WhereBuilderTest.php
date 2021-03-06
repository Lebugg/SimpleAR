<?php

use \Mockery as m;

use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\Expression\Func as FuncExpr;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Query;

use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;

/**
 * @coversDefaultClass SimpleAR\Database\Builder\WhereBuilder
 */
class WhereBuilderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers SimpleAR\Database\Builder\WhereBuilder::getQueryOptionRelationSeparator
     * @covers WhereBuilder::setQueryOptionRelationSeparator
     */
    public function testQueryOptionRelationSeparator()
    {
        $b = new WhereBuilder;

        $this->assertEquals(Cfg::get('queryOptionRelationSeparator'), $b->getQueryOptionRelationSeparator());

        $b->setQueryOptionRelationSeparator('#');
        $this->assertEquals('#', $b->getQueryOptionRelationSeparator());

        Cfg::set('queryOptionRelationSeparator', '@');
        $b = new WhereBuilder();
        $this->assertEquals('@', $b->getQueryOptionRelationSeparator());

        // Reset the default config option.
        Cfg::set('queryOptionRelationSeparator', '/');
    }

    /**
     * @covers WhereBuilder::separateAttributeFromRelations
     */
    public function testSeparateAttributeFromRelations()
    {
        $b = new WhereBuilder;

        $str = 'my/related/attribute';
        $this->assertEquals(['my/related', 'attribute'], $b->separateAttributeFromRelations($str));

        $str = 'myAttribute';
        $this->assertEquals(['', 'myAttribute'], $b->separateAttributeFromRelations($str));

        $b->setQueryOptionRelationSeparator('@');
        $this->assertEquals(['', $str], $b->separateAttributeFromRelations($str));
    }

    public function testRelationsToTableAlias()
    {
        $b = new WhereBuilder;
        $b->setQueryOptionRelationSeparator('/');

        $str = 'my/relations/are/very/nice';
        $expected = 'my.relations.are.very.nice';

        $this->assertEquals($expected, $b->relationsToTableAlias($str));
    }

    public function testSimpleCondition()
    {
        // One
        $options = [
            'root' => 'Article',
            'conditions' => [
                'authorId' => 12
            ],
        ];

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        //new SimpleCond('_', ['author_id'), '=', 12));
        $this->assertArrayHasKey('where', $components);
        $this->assertEquals($expected, $components['where']);

        // Two
        $options = [
            'root' => 'Article',
            'conditions' => [
                'authorId' => [12, 15, 16],
                'title' => 'Essays',
            ],
        ];

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected = [];
        $expected[] = ['type' => 'In', 'table' => '_', 'cols' => ['author_id'], 'val' => [12, 15, 16], 'logic' => 'AND', 'not' => false];
        //new SimpleCond('_', ['author_id'), '=', [12, 15, 16), 'AND');
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['title'], 'op' => '=', 'val' => 'Essays', 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', ['title'), '=', 'Essays', 'AND');
        $this->assertCount(2, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testSimpleConditionWithNullValues()
    {
        $options = [
            'root' => 'Article',
            'conditions' => [
                ['id', '!=', null],
                'title' => null,
            ],
        ];

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected = [];
        $expected[] = ['type' => 'Null', 'table' => '_', 'cols' => ['id'], 'logic' => 'AND', 'not' => true];
        $expected[] = ['type' => 'Null', 'table' => '_', 'cols' => ['title'], 'logic' => 'AND', 'not' => false];
        $this->assertCount(2, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    /**
     * @covers ::orWhere
     */
    public function testOrWhere()
    {
        $b = m::mock('\SimpleAR\Database\Builder\WhereBuilder[where]');
        $b->shouldReceive('where')->once()->with('my', '=', 12, 'OR', false);
        $b->orWhere('my', '=', 12);
    }

    /**
     * @covers ::andWhere
     */
    public function testAndWhere()
    {
        $b = m::mock('\SimpleAR\Database\Builder\WhereBuilder[where]');
        $b->shouldReceive('where')->once()->with('my', '=', 12, 'AND', false);
        $b->andWhere('my', '=', 12);
    }

    /**
     * @covers ::orWhereNot
     */
    public function testOrWhereNot()
    {
        $b = m::mock('\SimpleAR\Database\Builder\WhereBuilder[where]');
        $b->shouldReceive('where')->once()->with('my', '=', 12, 'OR', true);
        $b->orWhereNot('my', '=', 12);
    }

    /**
     * @covers ::andWhereNot
     */
    public function testAndWhereNot()
    {
        $b = m::mock('\SimpleAR\Database\Builder\WhereBuilder[where]');
        $b->shouldReceive('where')->once()->with('my', '=', 12, 'AND', true);
        $b->andWhereNot('my', '=', 12);
    }

    /**
     * @covers ::whereNested
     * @covers ::where
     */
    public function testWhereNested()
    {
        $b = new WhereBuilder;
        $b->whereNot(function($q) {
                $q->where('a', 12)
                  ->orWhere('b', 14);
            })
            ->orWhere(function ($q) {
                $q->where('a', 14)
                  ->andWhere('b', [12, 14]);
            })
            ;

        $tmp[] = ['type' => 'Basic', 'table' => '', 'cols' => ['a'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        $tmp[] = ['type' => 'Basic', 'table' => '', 'cols' => ['b'], 'op' => '=', 'val' => 14, 'logic' => 'OR', 'not' => false];
        $where[] = ['type' => 'Nested', 'nested' => $tmp, 'logic' => 'AND', 'not' => true];

        $tmp = [];
        $tmp[] = ['type' => 'Basic', 'table' => '', 'cols' => ['a'], 'op' => '=', 'val' => 14, 'logic' => 'AND', 'not' => false];
        $tmp[] = ['type' => 'In', 'table' => '', 'cols' => ['b'], 'val' => [12,14], 'logic' => 'AND', 'not' => false];
        $where[] = ['type' => 'Nested', 'nested' => $tmp, 'logic' => 'OR', 'not' => false];

        $components = $b->getComponents();
        $this->assertEquals($where, $components['where']);
    }

    public function testOrAndLogicalOps()
    {
        $b = new WhereBuilder;
        $options = [
            'root' => 'Article',
            'conditions' => [
                'authorId' => 12,
                'OR', 'blogId' => 15,
                'OR', [
                    ['authorId', '!=', [12, 15, 16]],
                    'title' => 'Alice in Wonderland',
                ]
            ],
        ];

        $components = $b->build($options);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['blog_id'], 'op' => '=', 'val' => 15, 'logic' => 'OR', 'not' => false];

        $nested[] = ['type' => 'In', 'table' => '_', 'cols' => ['author_id'], 'val' => [12,15,16], 'logic' => 'AND', 'not' => true];
        $nested[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['title'], 'op' => '=', 'val' => 'Alice in Wonderland', 'logic' => 'AND', 'not' => false];
        $expected[] = ['type' => 'Nested', 'nested' => $nested, 'logic' => 'OR', 'not' => false];

        $this->assertCount(3, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testAddInvolvedTable()
    {
        $b = new WhereBuilder;

        $b->setRootModel('Blog');
        $b->addInvolvedTable('articles.author');

        $this->assertEquals('Article', $b->getInvolvedModel('articles'));
        $this->assertEquals(Article::table(), $b->getInvolvedTable('articles'));
        $jc = (new JoinClause('articles', 'articles'))->on('_', 'id', 'articles', 'blog_id');
        $this->assertEquals($jc, $b->getJoinClause('articles'));

        $this->assertEquals('Author', $b->getInvolvedModel('articles.author'));
        $this->assertEquals(Author::table(), $b->getInvolvedTable('articles.author'));
        $jc = (new JoinClause('authors', 'articles.author'))->on('articles', 'author_id', 'articles.author', 'id');
        $this->assertEquals($jc, $b->getJoinClause('articles.author'));

    }

    public function testConditionOnRelations()
    {
        $b = new WhereBuilder;

        $opts = [
            'root' => 'Blog',
            'conditions' => [
                'name' => 'Glob',
                ['articles/title', 'LIKE', '%beer%'],
                [
                    'articles/author/id' => [12, 15, 16],
                    'OR', 'articles/author/firstName' => ['John'],
                ],
            ],
        ];

        $components = $b->build($opts);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['name'], 'op' => '=', 'val' => 'Glob', 'logic' => 'AND', 'not' => false];
        $expected[] = ['type' => 'Basic', 'table' => 'articles', 'cols' => ['title'], 'op' => 'LIKE', 'val' => '%beer%', 'logic' => 'AND', 'not' => false];
        $nested[] = ['type' => 'In', 'table' => 'articles.author', 'cols' => ['id'], 'val' => [12,15,16], 'logic' => 'AND', 'not' => false];
        $nested[] = ['type' => 'Basic', 'table' => 'articles.author', 'cols' => ['first_name'], 'op' => '=', 'val' => 'John', 'logic' => 'OR', 'not' => false];
        $expected[] = ['type' => 'Nested', 'nested' => $nested, 'logic' => 'AND', 'not' => false];

        $this->assertEquals($expected, $components['where']);
    }

    public function testWhereWithQueryAsValue()
    {
        $b = new SelectBuilder;
        $b->root('Blog');
        $subQuery = User::query(null, 'articles.readers')
            ->whereRelation(Article::relation('readers'), 'articles')
            ->addAggregate('AVG', 'age')->getQuery();
        $b->where('articles/author/age', '>', $subQuery);
        $b->get(['*'], false);

        $components = $b->build();
        $values = $b->getValues();

        $c = new BaseCompiler();
        $sql = $c->compileComponents($components, 'select');
        $val = $c->compileValues($values, 'select');

        $expected = 'SELECT `_`.* FROM `blogs` `_` INNER JOIN `articles` `articles` ON `_`.`id` = `articles`.`blog_id` INNER JOIN `authors` `articles.author` ON `articles`.`author_id` = `articles.author`.`id` WHERE `articles.author`.`age` > (SELECT AVG(`articles.readers`.`age`) FROM `USERS` `articles.readers` INNER JOIN `articles_USERS` `articles.readers_m` ON `articles.readers`.`id` = `articles.readers_m`.`user_id` WHERE `articles.readers_m`.`article_id` = `articles`.`id`)';
        $expectedValues = [[]];
        $this->assertEquals($expected, $sql);
        $this->assertEquals($expectedValues, $val);
    }

    public function testWhereAttribute()
    {
        $b = new WhereBuilder();
        $b->setRootAlias('__');
        $b->setInvolvedTable('_', Blog::table());
        $b->root('Article')->whereAttr('blogId', '_/id');

        $components = $b->build();
        $where[] = ['type' => 'Attribute', 'lTable' => '__', 'lCols' => ['blog_id'], 'op' => '=', 'rTable' => '_', 'rCols' => ['id'], 'logic' => 'AND'];

        $this->assertEquals($where, $components['where']);
    }

    /**
     * @covers ::whereExists
     */
    public function testWhereExists()
    {
        $subQuery = new Query(new SelectBuilder);
        $subQuery->setInvolvedTable('_', Blog::table());
        $subQuery->setRootAlias('__')
            ->root('Article')
            ->where('title', 'Article 1')
            ->whereAttr('blogId', '_/id');

        $b = new WhereBuilder;
        $b->root('Blog')->whereExists($subQuery);

        $components = $b->build();

        $where[] = ['type' => 'Exists', 'query' => $subQuery, 'logic' => 'AND', 'not' => false];
        // There is a sub array because Builders do not flatten value array.
        $val['where'] = [['Article 1']];

        $this->assertEquals($where, $components['where']);
        $this->assertEquals($val, $b->getValues());
    }

    /**
     * @covers ::whereNotExists
     */
    public function testWhereNotExists()
    {
        $subQuery = new Query(new SelectBuilder);
        $subQuery->setInvolvedTable('_', Blog::table());
        $subQuery->setRootAlias('__')
            ->root('Article')
            ->where('title', 'Article 1')
            ->whereAttr('blogId', '_/id');

        $b = new WhereBuilder;
        $b->root('Blog')->whereNotExists($subQuery);

        $components = $b->build();

        $where[] = ['type' => 'Exists', 'query' => $subQuery, 'logic' => 'AND', 'not' => true];
        // There is a sub array because Builders do not flatten value array.
        $val['where'] = [['Article 1']];

        $this->assertEquals($where, $components['where']);
        $this->assertEquals($val, $b->getValues());
    }

    public function testWhereWithArrayParameters()
    {
        // An array with one column will extract the column.
        $b = new WhereBuilder();
        $b->root('Article');
        $b->where(['id'], [12]);

        $where[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        $components = $b->build();

        $this->assertEquals($where, $components['where']);

        // An array with several columns will call whereTuple.
        $b = $this->getMock('SimpleAR\Database\Builder\WhereBuilder', ['whereTuple']);
        $b->expects($this->once())->method('whereTuple')
            ->with(['a', 'b'], [[1,2], ['x', 'y']]);
        $b->where(['a', 'b'], [[1,2], ['x', 'y']]);
    }

    public function testOperatorArrayficationChangesConditionType()
    {
        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('id', '=', [1, 2, 3]);

        $where[] = ['type' => 'In', 'table' => '_', 'cols' => ['id'], 'val' => [1,2,3], 'logic' => 'AND', 'not' => false];
        $components = $b->build();
        $this->assertEquals($where, $components['where']);
        $this->assertFalse($components['where'][0]['not']);

        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('id', '!=', [1, 2, 3]);

        $where = [];
        $where[] = ['type' => 'In', 'table' => '_', 'cols' => ['id'], 'val' => [1,2,3], 'logic' => 'AND', 'not' => true];
        $components = $b->build();
        $this->assertEquals($where, $components['where']);
    }

    public function testSimpleConditionWithoutUsingModel()
    {
        $b = new WhereBuilder;
        $b->root('USERS');
        $b->where('firstName', 'Jean');

        $expected = ['where' => [
            ['type' => 'Basic', 'table' => '', 'cols' => ['firstName'], 'op' => '=', 'val' => 'Jean', 'logic' => 'AND', 'not' => false],
        ]];

        $this->assertEquals($expected, $b->build());
    }

    public function testConditionWithExpressions()
    {
        $b = new WhereBuilder;
        $expr = new Expression('FN(attr) = val');
        $b->whereRaw($expr);

        $expected = [
            ['type' => 'Raw', 'val' => $expr, 'logic' => 'AND'],
        ];

        $components = $b->build();
        $this->assertEquals($expected, $components['where']);

        // With "conditions" option, now.
        $b = new WhereBuilder;
        $expr = new Expression('FN(attr) = val');
        $expr2 = new Expression('MONTH(date) = 2000');
        $conditions = [$expr, $expr2];
        $b->conditions($conditions);

        $expected = [
            ['type' => 'Raw', 'val' => $expr, 'logic' => 'AND'],
            ['type' => 'Raw', 'val' => $expr2, 'logic' => 'AND'],
        ];

        $components = $b->build();
        $this->assertEquals($expected, $components['where']);
    }

    /**
     * @expectedException SimpleAR\Exception\MalformedOptionException
     */
    public function testMalformedConditionsOption()
    {
        $b = new WhereBuilder();
        $conditions = ['countryId', '!=', 12]; // Surrounding parenthesis missing.
        $b->conditions($conditions);
        $b->build();
    }

    public function testWhereTuple()
    {
        $b = new WhereBuilder;
        $b->root('Article');
        $b->whereTuple(['authorId', 'blogId'], [[1,2], [1,3], [2,2]]);

        $expected = [
            [
                'type' => 'Tuple', 'table' => ['_', '_'],
                'cols' => ['author_id', 'blog_id'],
                'val' => [[1,2], [1,3], [2,2]],
                'logic' => 'AND', 'not' => false
            ],
        ];

        $components = $b->build();
        $this->assertEquals($expected, $components['where']);

        $b = new WhereBuilder;
        $b->root('Article');
        $b->whereTuple(['authorId', 'blog/id'], [[1,2], [1,3], [2,2]]);

        $where = [
            [
                'type' => 'Tuple', 'table' => ['_', 'blog'],
                'cols' => ['author_id', 'id'],
                'val' => [[1,2], [1,3], [2,2]],
                'logic' => 'AND', 'not' => false
            ],
        ];
        $from = [
            (new JoinClause('articles', '_')),
            (new JoinClause('blogs', 'blog'))->on('_', 'blog_id', 'blog', 'id'),
        ];

        $components = $b->build();
        $this->assertEquals($where, $components['where']);
        $this->assertEquals($from[1], $b->getJoinClause('blog'));
    }

    /**
     * Here we check which joins are made and that they are optimal.
     *
     * `Article::where('readers/id')->...` should include only middle table
     * `Article::where('readers/firstName')->...` should include both middle and
     * linked tables.
     *
     */
    public function no_testConditionOnManyMany()
    {
        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('readers/id', 12);
        $b->select(['*']);

        $b->build();

        // "_m" stands for "middle".
        $middle = (new JoinClause('articles_USERS', 'readers_m'))->on('_', 'id', 'readers_m', 'article_id');
        $lm = (new JoinClause('USERS', 'readers'))->on('readers_m', 'user_id', 'readers', 'id');

        $this->assertEquals($middle, $b->getJoinClause('readers_m'));
        $this->assertFalse(@ $b->getJoinClause('readers'));

        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('readers/firstName', 'John');
        $b->select(['*']);

        $b->build();

        $this->assertEquals($middle, $b->getJoinClause('readers_m'));
        $this->assertEquals($lm, $b->getJoinClause('readers'));
    }

    /**
     * Test the usage of FuncExpression in conditions.
     *
     * It validates the following syntax:
     *
     *  `$builder->where('DB::avg('articles/author/age'), '>', 25);`
     *  `$builder->whereAttr('thisOne', '<', DB::max('this/otherOne'));`
     *
     * @covers ::where
     * @covers ::whereAttr
     */
    public function testWhereFuncExpression()
    {
        $b = new WhereBuilder;
        $b->root('Blog');
        $b->where(DB::avg('articles/author/age'), '>', 25);
        $components = $b->getComponents();

        $expr = new FuncExpr('articles/author/age', 'AVG');
        $expr->setValue(['age']);
        $where = [[
            'type' => 'Basic', 'table' => 'articles.author', 'cols' => [$expr],
            'op' => '>', 'val' => 25, 'logic' => 'AND', 'not' => false
        ]];
        $this->assertEquals($where, $components['where']);

        // - - -

        $b = new WhereBuilder;
        $b->root('Article');
        $b->whereAttr('author/age', '<', DB::avg('readers/age'));

        $components = $b->getComponents();
        $expr = new FuncExpr('readers/age', 'AVG');
        $expr->setValue(['age']);
        $where = [[
            'type' => 'Attribute', 'lTable' => 'author', 'lCols' => ['age'],
            'op' => '<', 'rTable' => 'readers', 'rCols' => [$expr], 'logic' => 'AND'
        ]];
        $this->assertEquals($where, $components['where']);
    }

    /**
     * @covers ::whereFunc
     */
    public function testWhereFunc()
    {
        $b = new WhereBuilder;
        $b->root('Author');
        $b->whereFunc(DB::avg('age'), '>', 30);

        $components = $b->build();
        $expr = new FuncExpr('age', 'AVG');
        $expr->setValue(['age']);
        $where = [
            ['type' => 'Basic', 'table' => '_', 'cols' => [$expr], 'op' => '>', 'val' => 30, 'logic' => 'AND', 'not' => false],
        ];

        $this->assertEquals($where, $components['where']);
    }

    public function testWhereRelation()
    {
        $b = new WhereBuilder;
        $b->root('Article', 'articles');
        $b->whereRelation(Blog::relation('articles'), '_');

        $components = $b->build();
        $where[] = ['type' => 'Attribute', 'lTable' => 'articles', 'lCols' => ['blog_id'], 'op' => '=', 'rTable' => '_', 'rCols' => ['id'], 'logic' => 'AND'];

        $this->assertEquals($where, $components['where']);
        $this->assertEquals(Blog::table(), $b->getInvolvedTable('_'));

        // - - -

        // With many-to-many relation.
        $rel = Article::relation('readers');
        $mdName  = $rel->getMiddleTableName();
        $mdAlias = $rel->getMiddleTableAlias();

        $b = new WhereBuilder;
        $b->root('User', 'readers');
        $b->whereRelation($rel, '_');
        $components = $b->build();

        $jc = new JoinClause($mdName, $mdAlias);
        $jc->on('readers', 'id', $mdAlias, 'user_id');
        $where = [['type' => 'Attribute', 'lTable' => $mdAlias, 'lCols' => ['article_id'],
            'op' => '=', 'rTable' => '_', 'rCols' => ['id'],
            'logic' => 'AND']];

        $this->assertEquals($where, $components['where']);
        $this->assertEquals(Article::table(), $b->getInvolvedTable('_'));
        $this->assertEquals($jc, $b->getJoinClause($mdAlias));
        try {
            $b->getJoinClause('_');
            $this->fail('Should have thrown an exception.');
        } catch (Exception $ex) {
            $this->assertContains('_', $ex->getMessage());
        }
    }
}
