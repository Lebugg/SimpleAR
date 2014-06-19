<?php

/* use \SimpleAR\Database\Expression; */
/* use \SimpleAR\Database\Option; */
/* use \SimpleAR\Database\Option\Filter; */

/* class FilterTest extends PHPUnit_Framework_TestCase */
/* { */
/*     /1* private static $_sar; *1/ */

/*     /1* private static function _initializeSimpleAR() *1/ */
/*     /1* { *1/ */
/*     /1*     $cfg = new SimpleAR\Config(); *1/ */
/*     /1*     $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/../../db.json'), true); *1/ */
/*     /1*     $cfg->doForeignKeyWork = true; *1/ */
/*     /1*     $cfg->debug            = true; *1/ */
/*     /1*     $cfg->modelDirectory   = __DIR__ . '/../../models/'; *1/ */
/*     /1*     $cfg->dateFormat       = 'd/m/Y'; *1/ */

/*     /1*     self::$_sar = new SimpleAR($cfg); *1/ */
/*     /1* } *1/ */

/*     /1* public static function setUpBeforeClass() *1/ */
/*     /1* { *1/ */
/*     /1*     self::_initializeSimpleAR(); *1/ */
/*     /1* } *1/ */

/*     /1* public function testTrue() *1/ */
/*     /1* { *1/ */
/*     /1*     $this->assertTrue(TRUE); *1/ */
/*     /1* } *1/ */

/*     public function testFilterWithString() */
/*     { */
/*         $model = $this->getMockClass('ModelStub'); */
/*         $model::staticExpects($this->once())->method('columnsToSelect')->with('myFilter')->will($this->returnValue(array('author', 'title'))); */

/*         $option = new Filter('myFilter'); */
/*         $option->build(true, $model); */

/*         $this->assertEquals(array('author', 'title'), $option->attributes); */
/*         $this->assertFalse($option->toColumn); */

/*         $option = new Filter('myFilter'); */
/*         try */
/*         { */
/*             $option->build(false, $model); */
/*             $this->fail('Should have thrown an exception.'); */
/*         } catch (Exception $ex) {} */
/*     } */

/*     public function testFilterWithAttributeArray() */
/*     { */
/*         $option = new Filter(array('a', 'b')); */

/*         $option->build(true, 'Whatever'); */
/*         $this->assertEquals(array('a', 'b'), $option->attributes); */
/*         $this->assertTrue($option->toColumn); */

/*         $option->build(false); */
/*         $this->assertEquals(array('a', 'b'), $option->attributes); */
/*         $this->assertFalse($option->toColumn); */
/*     } */

/*     public function testFilterWithExpression() */
/*     { */
/*         $expr   = new Expression('a, b,c'); */
/*         $option = new Filter($expr); */

/*         $option->build(true, 'Whatever'); */
/*         $this->assertEquals(array('a', 'b', 'c'), $option->attributes); */
/*         $this->assertFalse($option->toColumn); */

/*         $option->build(false); */
/*         $this->assertFalse($option->toColumn); */

/*         $expr   = new Expression(array('a', 'b')); */
/*         $option = new Filter($expr); */
/*         $option->build(true, 'Whatever'); */
/*         $this->assertEquals(array('a', 'b'), $option->attributes); */
/*     } */

/*     public function testFilterWithBadValue() */
/*     { */
/*         $this->setExpectedException('\SimpleAR\Exception\MalformedOption'); */
/*         $option = new Filter(new StdClass()); */
/*         $option->build(true); */
/*     } */
/* } */

/* class ModelStub extends \SimpleAR\Orm\Model */
/* { */
/* } */
