<?php

class RelationTest extends PHPUnit_Framework_TestCase
{
    public function testScopeManipulation()
    {
        $rel = $this->getMockForAbstractClass('SimpleAR\Orm\Relation');

        $rel->setScope('recent');
        $this->assertEquals(['recent' => []], $rel->getScope());

        $rel->setScope(['recent', 'status' => 2]);
        $this->assertEquals(['recent' => [], 'status' => [2]], $rel->getScope());

        $rel->setScope(['recent', 'status' => [2]]);
        $this->assertEquals(['recent' => [], 'status' => [2]], $rel->getScope());
    }
}
