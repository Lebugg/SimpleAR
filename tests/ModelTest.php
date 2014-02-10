<?php

class ModelTest extends PHPUnit_Framework_TestCase
{
    public function testAttributeManipulation()
    {
        $stub = $this->getMockForAbstractClass('SimpleAR\Model');

        $stub->foo = 'bar';
        $this->assertEquals($stub->foo, 'bar');
        
        // Test __isset() and __unset().
        $this->assertTrue(isset($stub->foo));
        unset($stub->foo);
        $this->assertFalse(isset($stub->foo));
    }

    public function testIsDirtyFlag()
    {
        $stub = $this->getMockForAbstractClass('SimpleAR\Model');

        $this->assertFalse($stub->isDirty());
        $stub->foo = 'bar';
        $this->assertTrue($stub->isDirty());
    }
}
