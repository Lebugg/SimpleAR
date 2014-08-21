<?php

class DatabaseBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testClearResultAtEachBuild()
    {
        $b = $this->getMock('SimpleAR\Database\Builder', ['cleanComponents']);
        $b->expects($this->once())->method('cleanComponents');
        $b->build();
    }
}
