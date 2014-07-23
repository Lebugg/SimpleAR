<?php

use \SimpleAR\Orm\Relation\ManyMany;

class ManyManyTest extends PHPUnit_Framework_TestCase
{
    public function testIsToMany()
    {
        $r = new ManyMany;
        $this->assertTrue($r->isToMany());
    }
}
