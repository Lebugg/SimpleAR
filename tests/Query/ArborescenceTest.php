<?php

use \SimpleAR\Query\Arborescence;

class ArborescenceTest extends PHPUnit_Framework_TestCase
{
    public function testNewArborescence()
    {
        $a = new Arborescence('Article');

        $this->assertTrue($a->isRoot());
        $this->assertTrue($a->isLeaf());
    }

    public function testChildManipulation()
    {
        $root  = new Arborescence('Blog');
        $child = $root->add(array('articles'));

        $this->assertTrue($root->isRoot());
        $this->assertFalse($root->isLeaf());

        $this->assertFalse($child->isRoot());
        $this->assertTrue($child->isLeaf());

        $child2 = $root->getChild('articles');
        $this->assertEquals($child, $child2);
    }

}
