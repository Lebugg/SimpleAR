<?php

use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Database\Query;
use \SimpleAR\Database\Condition\Simple as SimpleCond;
use \SimpleAR\Database\Condition\Nested as NestedCond;
use \SimpleAR\Database\JoinClause;

use \SimpleAR\Facades\Cfg;

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

        $components = $this->_builder->build($options);

        $expected = array(new SimpleCond('_', array('author_id'), '=', 12));
        $this->assertArrayHasKey('where', $components);
        $this->assertEquals($expected, $components['where']);

        $query = new Query();

        $options = array(
            'root' => 'Article',
            'conditions' => array(
                'authorId' => array(12, 15, 16),
                'title' => 'Essays',
            ),
        );

        $components =$this->_builder->build($options);

        $expected = array();
        $expected[] = new SimpleCond('_', array('author_id'), '=', array(12, 15, 16), 'AND');
        $expected[] = new SimpleCond('_', array('title'), '=', 'Essays', 'AND');
        $this->assertCount(2, $components['where']);
        $this->assertEquals($expected, $components['where']);
    }

    public function testOrAndLogicalOps()
    {
        $query = new Query();

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
        $jc = (new JoinClause('articles', 'articles'))->on('', 'id', 'articles', 'blog_id');
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
}
