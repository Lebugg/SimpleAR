<?php

use \SimpleAR\Facades\Cfg;

class CfgTest extends PHPUnit_Framework_TestCase
{
    private static $_sar;

    private static function _initializeSimpleAR()
    {
        $cfg = new SimpleAR\Config();
        $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/../db.json'), true);
        //$cfg->modelDirectory   = __DIR__ . '/models/';

        self::$_sar = new SimpleAR($cfg);
    }

    public static function setUpBeforeClass()
    {
        self::_initializeSimpleAR();
    }

    public function testOptionGetSet()
    {
        $this->assertEquals(self::$_sar->cfg->charset, Cfg::get('charset'));
        
        Cfg::set('charset', 'iso');
        $this->assertEquals('iso', self::$_sar->cfg->charset);
    }
}
