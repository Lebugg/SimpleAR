<?php

use \SimpleAR\Orm\Relation\BelongsTo;

class BelongsToTest extends PHPUnit_Framework_TestCase
{
    public function testIsToMany()
    {
        $r = new BelongsTo;
        $this->assertFalse($r->isToMany());
    }
}
