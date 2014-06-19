<?php

/* class SelectTest extends PHPUnit_Framework_TestCase */
/* { */
/*     public function testSelectOptionMethod() */
/*     { */
/*         $select = $this->getMock('\SimpleAR\Database\Builder\Select', array('option')); */
/*         $select->expects($this->at(0))->method('option')->with('filter', array('blog_id', 'content'), false); */
/*         $select->expects($this->at(1))->method('option')->with('filter', array('blog_id', 'content'), false); */
/*         $select->expects($this->at(2))->method('option')->with('filter', 'restricted', false); */
/*         $select->expects($this->at(3))->method('option')->with('limit', 5, false); */

/*         $select->root('Article') */
/*                ->filter('blog_id', 'content') */
/*                ->filter(array('blog_id', 'content')) */
/*                ->filter('restricted') */
/*                ->limit(5) */
/*                ; */
/*     } */

/*     public function testSelectQueryStringBuild() */
/*     { */
/*         $select = $this->getMock('\SimpleAR\Database\Builder\Select', array('execute')); */
/*         $select->expects($this->once())->method('execute')->with($this->stringContains('SELECT', false))->will($this->returnValue($select)); */

/*         $select->root('Article') */
/*                ->filter('author', 'title') */
/*                ->conditions(array('author' => 'John Doe')) */
/*                ->limit(3) */
/*                ->run() */
/*                ; */

/*        $sql = $select->getSql(); */
/*        $this->assertContains('SELECT', $sql); */
/*        $this->assertContains('FROM', $sql); */
/*        $this->assertContains('WHERE', $sql); */
/*        $this->assertContains('LIMIT', $sql); */
/*        $this->assertEquals(array('John Doe'), $select->getValues()); */
/*     } */

/*     public function testSelectBuildWithRelation() */
/*     { */
/*         $select = $this->getMock('\SimpleAR\Database\Builder\Select', array('execute')); */
/*         $select->expects($this->once())->method('execute')->with($this->stringContains('SELECT', false))->will($this->returnValue($select)); */

/*         $select->root('Blog') */
/*                ->filter('author') */
/*                ->conditions(array('articles/author' => 'John Doe')) */
/*                ->limit(2) */
/*                ->offset(1) */
/*                ->run() */
/*                ; */

/*        $sql = $select->getSql(); */
/*        $this->assertContains('SELECT', $sql); */
/*        $this->assertContains('FROM', $sql); */
/*        $this->assertContains('WHERE', $sql); */
/*        $this->assertContains('LIMIT', $sql); */
/*        $this->assertContains('OFFSET', $sql); */
/*        $this->assertEquals(array('John Doe'), $select->getValues()); */
/*     } */
/* } */
