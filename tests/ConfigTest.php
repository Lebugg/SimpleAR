<?php

use \SimpleAR\Config;

/**
 * @covers Config
 */
class ConfigTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Config::__get
     * @covers Config::__set
     */
    public function testOptionGetSet()
    {
        $cfg = new Config();

        $cfg->foreignKeySuffix = '_fk';
        $this->assertEquals('_fk', $cfg->foreignKeySuffix);

        $cfg->foreignKeySuffix = '_id';
        $this->assertEquals('_id', $cfg->foreignKeySuffix);
    }

    public function testBuildForeignKeyOption()
    {
        $cfg = new Config();
        $cfg->foreignKeySuffix = 'Id';

        $fn = $cfg->buildForeignKey;

        $this->assertEquals('blogId', $fn('Blog'));
    }

    public function testClassAliases()
    {
        $this->assertFalse(class_exists('DB'));
        $this->assertFalse(class_exists('SimpleAR\Model'));
        $this->assertFalse(class_exists('Cfg'));

        $cfg = new Config();
        $cfg->aliases = array(
            'SimpleAR\Facades\DB' => 'DB',
            'SimpleAR\Orm\Model'  => 'SimpleAR\Model',
        );
        $cfg->apply();

        $this->assertTrue(class_exists('DB'));
        $this->assertTrue(class_exists('SimpleAR\Model'));
        $this->assertFalse(class_exists('Cfg'));
    }
}
