<?php

/* use \SimpleAR\Database\Expression; */
/* use \SimpleAR\Database\Option\Group; */

/* class GroupTest extends PHPUnit_Framework_TestCase */
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

/*     public function testGroupUse() */
/*     { */
/*         $expr = new Expression('a.m_field'); */
/*         $option = new Group($expr); */
/*         $option->build(true, null); */

/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'attribute' => 'a.m_field', */
/*                     'toColumn'  => false, */
/*                     'relations' => array(), */
/*                 ), */
/*             ), */
/*             $option->groups */
/*         ); */

/*         $option = new Group('my/relation/field'); */
/*         $option->build(true, null); */
/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'attribute' => 'field', */
/*                     'toColumn'  => true, */
/*                     'relations' => array('my', 'relation'), */
/*                 ), */
/*             ), */
/*             $option->groups */
/*         ); */
/*         $option = new Group(array('my/relation/field', 'other_field')); */
/*         $option->build(true, null); */
/*         $this->assertEquals( */
/*             array( */
/*                 array( */
/*                     'attribute' => 'field', */
/*                     'toColumn'  => true, */
/*                     'relations' => array('my', 'relation'), */
/*                 ), */
/*                 array( */
/*                     'attribute' => 'other_field', */
/*                     'toColumn'  => true, */
/*                     'relations' => array(), */
/*                 ), */
/*             ), */
/*             $option->groups */
/*         ); */
/*     } */
/* } */
