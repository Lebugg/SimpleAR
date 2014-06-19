<?php

/* use \SimpleAR\Database\Expression; */
/* use \SimpleAR\Database\Option\Order; */

/* class OrderTest extends PHPUnit_Framework_TestCase */
/* { */
/*     public function testExpressionValue() */
/*     { */
/*         $expr = new Expression('RAND()'); */
/*         $opt  = new Order($expr); */
/*         $opt->build(false, null); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array(), */
/*                     'attribute' => 'RAND()', */
/*                     'toColumn'  => false, */
/*                 ), */
/*             ), */
/*             $opt->orders */
/*         ); */

/*         $opt = new Order(array( */
/*             'last_name', */
/*             'first_name'           => 'DESC', */
/*             'other/relation/field' => 'ASC' */
/*         )); */
/*         $opt->build(false, null); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array(), */
/*                     'attribute' => 'last_name', */
/*                     'toColumn'  => false, */
/*                     'direction' => 'ASC', */
/*                 ), */
/*                 array( */
/*                     'relations' => array(), */
/*                     'attribute' => 'first_name', */
/*                     'toColumn'  => false, */
/*                     'direction' => 'DESC', */
/*                 ), */
/*                 array( */
/*                     'relations' => array('other', 'relation'), */
/*                     'attribute' => 'field', */
/*                     'toColumn'  => false, */
/*                     'direction' => 'ASC', */
/*                 ), */
/*             ), */
/*             $opt->orders */
/*         ); */
/*     } */

/*     public function testOrderWithUsingModel() */
/*     { */
/*         $opt = new Order('last_name'); */
/*         $opt->build(true, 'Article'); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array(), */
/*                     'attribute' => 'title', */
/*                     'toColumn'  => true, */
/*                     'direction' => 'ASC', */
/*                 ), */
/*                 array( */
/*                     'relations' => array(), */
/*                     'attribute' => 'last_name', */
/*                     'toColumn'  => true, */
/*                     'direction' => 'ASC', */
/*                 ), */
/*             ), */
/*             $opt->orders */
/*         ); */
/*     } */

/*     public function testOrderCount() */
/*     { */
/*         $opt = new Order('my/#articles'); */
/*         $opt->build(true, 'Blog'); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array('my', 'articles'), */
/*                     'attribute' => 'id', */
/*                     'toColumn'  => true, */
/*                     'fn' => 'COUNT', */

/*                     'asRelations' => array('my'), */
/*                     'asAttribute' => '#articles', */
/*                 ), */
/*             ), */
/*             $opt->aggregates */
/*         ); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array('my'), */
/*                     'attribute' => 'id', */
/*                     'toColumn'  => true, */
/*                 ), */
/*             ), */
/*             $opt->groups */
/*         ); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'relations' => array('my'), */
/*                     'attribute' => '#articles', */
/*                     'toColumn'  => false, */
/*                     'direction' => 'ASC', */
/*                 ), */
/*             ), */
/*             $opt->orders */
/*         ); */
/*     } */
/* } */
