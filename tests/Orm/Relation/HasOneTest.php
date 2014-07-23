<?php

use \SimpleAR\Orm\Relation\HasOne;

class HasOneTest extends PHPUnit_Framework_TestCase
{
    public function testIsToMany()
    {
        $r = new HasOne;
        $this->assertFalse($r->isToMany());
    }
}
