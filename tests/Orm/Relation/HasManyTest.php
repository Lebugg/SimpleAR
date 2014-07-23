<?php

use \SimpleAR\Orm\Relation\HasMany;

class HasManyTest extends PHPUnit_Framework_TestCase
{
    public function testIsToMany()
    {
        $r = new HasMany;
        $this->assertTrue($r->isToMany());
    }
}
