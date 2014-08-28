<?php

use SimpleAR\Database\Builder\WhereBuilder;
use SimpleAR\Database\Builder\SelectBuilder;
use SimpleAR\Database\Compiler\BaseCompiler;
use SimpleAR\Database\Expression;
use SimpleAR\Database\JoinClause;
use SimpleAR\Database\Query;

use SimpleAR\Facades\Cfg;
use SimpleAR\Facades\DB;

class WhereBuilderTest extends PHPUnit_Framework_TestCase
{
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

        $where[] = ['type' => 'Exists', 'query' => $subQuery, 'logic' => 'AND'];
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

        $expected = ['root' => 'USERS', 'where' => [
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
}
