<?php

use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\Builder\SelectBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Query;
use \SimpleAR\Database\Condition\Simple as SimpleCond;
use \SimpleAR\Database\Condition\Nested as NestedCond;
use \SimpleAR\Database\Condition\Exists as ExistsCond;
use \SimpleAR\Database\Condition\Attribute as AttrCond;
use \SimpleAR\Database\JoinClause;

use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;

class WhereBuilderTest extends PHPUnit_Framework_TestCase
{
    private $_builder;
    private $_compiler;
    private $_conn;

    public function setUp()
    {
        global $sar;

        $this->_builder = new WhereBuilder();
        $this->_compiler = new BaseCompiler();
        $this->_conn = $sar->db->connection();
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
        $options = array(
            'root' => 'Article',
            'conditions' => array(
                'authorId' => 12
            ),
        );

        $b = new WhereBuilder();
        $components = $b->build($options);

        $expected = array(new SimpleCond('_', array('author_id'), '=', 12));
        $this->assertArrayHasKey('where', $components);
        $this->assertEquals($expected, $components['where']);

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
        $expected[] = new SimpleCond('_', array('author_id'), '=', array(12, 15, 16), 'AND');
        $expected[] = new SimpleCond('_', array('title'), '=', 'Essays', 'AND');
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

        $expected[] = new SimpleCond('_', array('author_id'), '=', 12, 'AND');
        $expected[] = new SimpleCond('_', array('blog_id'), '=', 15, 'OR');

        $nested[] = new SimpleCond('_', array('author_id'), '!=', array(12, 15, 16), 'AND');
        $nested[] = new SimpleCond('_', array('title'), '=', 'Alice in Wonderland', 'AND');
        $expected[] = new NestedCond($nested, 'OR');

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

        $expected[] = new SimpleCond('_', 'name', '=', 'Glob', 'AND');
        $expected[] = new SimpleCond('articles', array('title'), 'LIKE', '%beer%', 'AND');
        $expected[] = new NestedCond(array(
            new SimpleCond('articles.author', array('id'), '=', array(12,15,16), 'AND'),
            new SimpleCond('articles.author', array('first_name'), '=', array('John'), 'OR'),
        ), 'AND');

        $this->assertEquals($expected, $components['where']);
    }

    public function testWhereAttribute()
    {
        $b = new WhereBuilder();
        $b->setRootAlias('__');
        $b->setInvolvedTable('_', Blog::table());
        $b->root('Article')->whereAttr('blogId', '_/id');

        $components = $b->build();

        $where[] = new AttrCond('__', 'blog_id', '=', '_', 'id', 'AND');

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

        $where[] = new ExistsCond($subQuery);
        // There is a sub array because Builders do not flatten value array.
        $val[] = array('Article 1');

        $this->assertEquals($where, $components['where']);
        $this->assertEquals($val, $b->getValues());
    }

    public function testWhereMethodWithArrayParameters()
    {
        $b = new WhereBuilder();
        $b->root('Article');
        $b->where(array('id'), array(12));

        $where[] = new SimpleCond('_', 'id', '=', 12);
        $components = $b->build();

        $this->assertEquals($where, $components['where']);
    }
}
