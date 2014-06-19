<?php

/* use \SimpleAR\Database\Expression; */
/* use \SimpleAR\Database\Option\With; */

/* class WithTest extends PHPUnit_Framework_TestCase */
/* { */
/*     public function testWithNotUsingModel() */
/*     { */
/*         $opt = new With('something'); */
/*         try */
/*         { */
/*             $opt->build(false, true); */
/*             $this->fail('Should have thrown an MalformedOption exception'); */
/*         } */
/*         catch (\SimpleAR\Exception\MalformedOption $ex) {} */
/*     } */

/*     public function testWithOneValue() */
/*     { */
/*         $opt = new With('with/this'); */
/*         $opt->build(true, null); */

/*         $this->assertEquals(array( */
/*             array( */
/*                 'relations' => array('with', 'this'), */
/*             ), */
/*         ), $opt->withs); */
/*     } */

/*     public function testWithSeveralValues() */
/*     { */
/*         $opt = new With(array('with/this', 'and/with/that', 'too')); */
/*         $opt->build(true, null); */

/*         $this->assertEquals(array( */
/*             array( */
/*                 'relations' => array('with', 'this'), */
/*             ), */
/*             array( */
/*                 'relations' => array('and', 'with', 'that'), */
/*             ), */
/*             array( */
/*                 'relations' => array('too'), */
/*             ), */
/*         ), $opt->withs); */
/*     } */

/*     public function testWithCount() */
/*     { */
/*         $opt = new With('with/#these'); */
/*         $opt->build(true, null); */

/*         $this->assertEquals(array( */
/*             array( */
/*                 'relations' => array('with', 'these'), */
/*                 'attribute' => 'id', */
/*                 'toColumn'  => true, */
/*                 'fn' => 'COUNT', */
/*                 'asRelations' => array('with'), */
/*                 'asAttribute' => '#these', */
/*             ), */
/*         ), $opt->aggregates); */
/*         $this->assertEquals(array( */
/*             array( */
/*                 'relations' => array('with'), */
/*                 'attribute' => 'id', */
/*                 'toColumn'  => true, */
/*             ), */
/*         ), $opt->groups); */

/*         $this->assertEquals(array( */
/*             array( */
/*                 'relations' => array('with'), */
/*             ), */
/*         ), $opt->withs); */
/*     } */
/* } */
