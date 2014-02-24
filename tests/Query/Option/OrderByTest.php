<?php
date_default_timezone_set('Europe/Paris');

use \SimpleAR\Database\Expression;
use \SimpleAR\Query\Option;
use \SimpleAR\Query\Option\Order;

class OrderTest extends PHPUnit_Framework_TestCase
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

    public function testExpressionValue()
    {
        $expr = new Expression('RAND()');
        $orderBy = Option::forge('order', $expr, null);

        $this->assertEquals(array('RAND()'), $orderBy->orders);
    }
}
