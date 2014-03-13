<?php

use \SimpleAR\Database\Expression;
use \SimpleAR\Query\Option\Group;

class GroupTest extends PHPUnit_Framework_TestCase
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

    public function testGroupUse()
    {
        $expr = new Expression('a.m_field');
        $option = new Group($expr);
        $option->build(true, null);

        $this->assertEquals(
            array(
                array(
                    'attribute' => 'a.m_field',
                    'toColumn'  => false,
                    'relations' => array(),
                ),
            ),
            $option->groups
        );

        $option = new Group('my/relation/field');
        $option->build(true, null);
        $this->assertEquals(
            array(
                array(
                    'attribute' => 'field',
                    'toColumn'  => true,
                    'relations' => array('my', 'relation'),
                ),
            ),
            $option->groups
        );
        $option = new Group(array('my/relation/field', 'other_field'));
        $option->build(true, null);
        $this->assertEquals(
            array(
                array(
                    'attribute' => 'field',
                    'toColumn'  => true,
                    'relations' => array('my', 'relation'),
                ),
                array(
                    'attribute' => 'other_field',
                    'toColumn'  => true,
                    'relations' => array(),
                ),
            ),
            $option->groups
        );
    }
}
