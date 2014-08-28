<?php

use SimpleAR\Database\Builder;

class DatabaseBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSetOptionTwiceMergeOptionValues()
    {
        $b = new Builder();

        $b->conditions(['attr' => 12]);
        $b->conditions(['attr2' => 15]);

        $expected = ['conditions' => ['attr' => 12, 'attr2' => 15]];
        $this->assertEquals($expected, $b->getOptions());
    }
}
