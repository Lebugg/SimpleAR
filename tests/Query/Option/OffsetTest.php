<?php

use \SimpleAR\Database\Expression;
use \SimpleAR\Exception\MalformedOption;
use \SimpleAR\Query\Option\Offset;

class OffsetTest extends PHPUnit_Framework_TestCase
{
    private static $_sar;

    private static function _initializeSimpleAR()
    {
        $cfg = new SimpleAR\Config();
        $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/../../db.json'), true);
        $cfg->doForeignKeyWork = true;
        $cfg->debug            = true;
        $cfg->modelDirectory   = __DIR__ . '/../../models/';
        $cfg->dateFormat       = 'd/m/Y';

        self::$_sar = new SimpleAR($cfg);
    }

    public static function setUpBeforeClass()
    {
        self::_initializeSimpleAR();
    }

    public function testOffsetValue()
    {
        $option = new Offset(12);
        $option->build(false, null);
        $this->assertEquals(12, $option->offset);

        $option = new Offset(-1);
        try
        {
            $option->build(false, null);
            $this->fail('Should throw an exception!');
        }
        catch (MalformedOption $ex) {}
    }
}
