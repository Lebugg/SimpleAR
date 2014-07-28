<?php

use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Query;
use \SimpleAR\Database\JoinClause;

use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;

class WhereBuilderTest extends PHPUnit_Framework_TestCase
{
    private $_builder;

    public function setUp()
    {
        global $sar;

        $this->_builder = new WhereBuilder();
    }

    public function testQueryOptionRelationSeparator()
    {
        $b = $this->_builder;

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
        $b = $this->_builder;

        $str = 'my/related/attribute';
        $this->assertEquals(array('my/related', 'attribute'), $b->separateAttributeFromRelations($str));

        $str = 'myAttribute';
        $this->assertEquals(array('', 'myAttribute'), $b->separateAttributeFromRelations($str));

        $b->setQueryOptionRelationSeparator('@');
        $this->assertEquals(array('', $str), $b->separateAttributeFromRelations($str));
    }

    public function testRelationsToTableAlias()
    {
        $b = $this->_builder;
        $b->setQueryOptionRelationSeparator('/');

        $str = 'my/relations/are/very/nice';
        $expected = 'my.relations.are.very.nice';

        $this->assertEquals($expected, $b->relationsToTableAlias($str));
    }

    public function testSimpleCondition()
    {
        // One
        $options = array(
            'root' => 'Article',
            'conditions' => array(
                'authorId' => 12
            ),
        );

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        //new SimpleCond('_', array('author_id'), '=', 12));
        $this->assertArrayHasKey('where', $components);
        $this->assertEquals($expected, $components['where']);

        // Two
        $options = array(
            'root' => 'Article',
            'conditions' => array(
                'authorId' => array(12, 15, 16),
                'title' => 'Essays',
            ),
        );

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected = array();
        $expected[] = ['type' => 'In', 'table' => '_', 'cols' => ['author_id'], 'val' => [12, 15, 16], 'logic' => 'AND', 'not' => false];
        //new SimpleCond('_', array('author_id'), '=', array(12, 15, 16), 'AND');
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['title'], 'op' => '=', 'val' => 'Essays', 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', array('title'), '=', 'Essays', 'AND');
        $this->assertCount(2, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testSimpleConditionWithNullValues()
    {
        $options = array(
            'root' => 'Article',
            'conditions' => array(
                array('id', '!=', null),
                'title' => null,
            ),
        );

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected = array();
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['id'], 'op' => '!=', 'val' => null, 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', array('id'), '!=', null, 'AND');
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['title'], 'op' => '=', 'val' => null, 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', array('title'), '=', null, 'AND');
        $this->assertCount(2, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testOrAndLogicalOps()
    {
        $options = array(
            'root' => 'Article',
            'conditions' => array(
                'authorId' => 12,
                'OR', 'blogId' => 15,
                'OR', array(
                    array('authorId', '!=', array(12, 15, 16)),
                    'title' => 'Alice in Wonderland',
                )
            ),
        );

        $components =$this->_builder->build($options);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['author_id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', array('author_id'), '=', 12, 'AND');
        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['blog_id'], 'op' => '=', 'val' => 15, 'logic' => 'OR', 'not' => false];
        // $expected[] = new SimpleCond('_', array('blog_id'), '=', 15, 'OR');

        $nested[] = ['type' => 'In', 'table' => '_', 'cols' => ['author_id'], 'val' => [12,15,16], 'logic' => 'AND', 'not' => true];
        //$nested[] = new SimpleCond('_', array('author_id'), '!=', array(12, 15, 16), 'AND');
        $nested[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['title'], 'op' => '=', 'val' => 'Alice in Wonderland', 'logic' => 'AND', 'not' => false];
        //$nested[] = new SimpleCond('_', array('title'), '=', 'Alice in Wonderland', 'AND');
        $expected[] = ['type' => 'Nested', 'nested' => $nested, 'logic' => 'OR', 'not' => false];
        //$expected[] = new NestedCond($nested, 'OR');

        $this->assertCount(3, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testAddInvolvedTable()
    {
        $b = $this->_builder;

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
        $b = $this->_builder;

        $opts = array(
            'root' => 'Blog',
            'conditions' => array(
                'name' => 'Glob',
                array('articles/title', 'LIKE', '%beer%'),
                array(
                    'articles/author/id' => array(12, 15, 16),
                    'OR', 'articles/author/firstName' => array('John'),
                ),
            ),
        );

        $components = $b->build($opts);

        $expected[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['name'], 'op' => '=', 'val' => 'Glob', 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('_', 'name', '=', 'Glob', 'AND');
        $expected[] = ['type' => 'Basic', 'table' => 'articles', 'cols' => ['title'], 'op' => 'LIKE', 'val' => '%beer%', 'logic' => 'AND', 'not' => false];
        //$expected[] = new SimpleCond('articles', array('title'), 'LIKE', '%beer%', 'AND');
        $nested[] = ['type' => 'In', 'table' => 'articles.author', 'cols' => ['id'], 'val' => [12,15,16], 'logic' => 'AND', 'not' => false];
        $nested[] = ['type' => 'In', 'table' => 'articles.author', 'cols' => ['first_name'], 'val' => ['John'], 'logic' => 'OR', 'not' => false];
        $expected[] = ['type' => 'Nested', 'nested' => $nested, 'logic' => 'AND', 'not' => false];
        // $expected[] = new NestedCond(array(
        //     new SimpleCond('articles.author', array('id'), '=', array(12,15,16), 'AND'),
        //     new SimpleCond('articles.author', array('first_name'), '=', array('John'), 'OR'),
        // ), 'AND');

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
        //$where[] = new AttrCond('__', 'blog_id', '=', '_', 'id', 'AND');

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
        //$where[] = new ExistsCond($subQuery);
        // There is a sub array because Builders do not flatten value array.
        $val['where'] = array(array('Article 1'));

        $this->assertEquals($where, $components['where']);
        $this->assertEquals($val, $b->getValues());
    }

    public function testWhereMethodWithArrayParameters()
    {
        $b = new WhereBuilder();
        $b->root('Article');
        $b->where(array('id'), array(12));

        $where[] = ['type' => 'Basic', 'table' => '_', 'cols' => ['id'], 'op' => '=', 'val' => 12, 'logic' => 'AND', 'not' => false];
        //$where[] = new SimpleCond('_', 'id', '=', 12);
        $components = $b->build();

        $this->assertEquals($where, $components['where']);
    }

    public function testOperatorArrayficationChangesConditionType()
    {
        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('id', '=', array(1, 2, 3));

        $where[] = ['type' => 'In', 'table' => '_', 'cols' => ['id'], 'val' => [1,2,3], 'logic' => 'AND', 'not' => false];
        $components = $b->build();
        $this->assertEquals($where, $components['where']);
        $this->assertFalse($components['where'][0]['not']);

        $b = new WhereBuilder;
        $b->root('Article');
        $b->where('id', '!=', array(1, 2, 3));

        $where = array();
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
}
