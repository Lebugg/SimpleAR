<?php

use \SimpleAR\Config;

class ConfigTest extends PHPUnit_Framework_TestCase
{
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
    
    /*
    public function testUnknownOption()
    {
        $cfg = new Config();

        $foo = $cfg->optionThatDoesNotExist;
        $cfg->optionThatDoesNotExist = 'foo';
    }
    */
}
