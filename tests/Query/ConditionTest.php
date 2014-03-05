<?php

use \SimpleAR\Query\Condition\SimpleCondition;
use \SimpleAR\Query\Condition\ConditionGroup;
use \SimpleAR\Query\Condition\Attribute;
use \SimpleAR\Facades\DB;

class ConditionTest extends PHPUnit_Framework_TestCase
{
    private static $_sar;

    private static function _initializeSimpleAR()
    {
        $cfg = new SimpleAR\Config();
        $cfg->dsn              = json_decode(file_get_contents(__DIR__ . '/../db.json'), true);
        $cfg->doForeignKeyWork = true;
        $cfg->debug            = true;
        $cfg->modelDirectory   = __DIR__ . '/../models/';
        $cfg->dateFormat       = 'd/m/Y';

        self::$_sar = new SimpleAR($cfg);
    }

    public static function setUpBeforeClass()
    {
        self::_initializeSimpleAR();
    }
    public function testConditionAddAttribute()
    {
        $cond = new SimpleCondition();
        $this->assertCount(0, $cond->attributes);

        $cond->addAttribute(new Attribute('name', 1, '='));
        $cond->addAttribute(new Attribute('name2', 2, '='));

        $this->assertCount(2, $cond->attributes);
    }

    public function testMergeConditions()
    {
        $cond = new SimpleCondition();
        $cond->addAttribute(new Attribute('name', 1, '='));
        $cond->addAttribute(new Attribute('name', 1, '='));

        $cond2 = new SimpleCondition();
        $cond2->addAttribute(new Attribute('name', 1, '='));
        $cond2->addAttribute(new Attribute('name', 1, '='));

        $cond->merge($cond2);
        $this->assertCount(4, $cond->attributes);
        $this->assertCount(2, $cond2->attributes);
    }

    public function testConditionGroupType()
    {
        $group = new ConditionGroup(ConditionGroup::T_AND);
        $this->assertEquals(ConditionGroup::T_AND, $group->type());

        $group = new ConditionGroup(ConditionGroup::T_OR);
        $this->assertEquals(ConditionGroup::T_OR, $group->type());
        
        try {
            $group = new ConditionGroup('wrong value');
            $this->fail('Should throw an Exception');
        } catch (Exception $ex) {}
    }

    public function testConditionGroupAddConditions()
    {
        $group = new ConditionGroup(ConditionGroup::T_AND);
        $this->assertCount(0, $group->elements());
        $this->assertTrue($group->isEmpty());

        $group->add(new SimpleCondition());
        $this->assertCount(1, $group->elements());
        $this->assertFalse($group->isEmpty());

        $group->add(new SimpleCondition());
        $group->add(new ConditionGroup(ConditionGroup::T_AND));
        $this->assertCount(3, $group->elements());
    }

    public function testConditionGroupMergeWithConditionCallsAddMethod()
    {
        $cond  = new SimpleCondition();
        $group = $this->getMock('\SimpleAR\Query\Condition\ConditionGroup', array('add'), array(ConditionGroup::T_AND));

        $group->expects($this->once())->method('add')->with($this->identicalTo($cond));
        $group->merge($cond);
    }

    public function testConditionGroupMergeWithOtherGroup()
    {
        $g = new ConditionGroup(ConditionGroup::T_AND);
        $g->add(new SimpleCondition());
        $g->add(new SimpleCondition());
        $g->add(new ConditionGroup(ConditionGroup::T_AND));

        $gg = new ConditionGroup(ConditionGroup::T_AND);
        $gg->add(new SimpleCondition());
        $gg->add(new SimpleCondition());

        $g->merge($gg);

        $this->assertCount(5, $g->elements());
    }

    public function testConditionwithExpression()
    {
        $c = new SimpleCondition();
        $c->addExpression(DB::expr('my condition = true'));
        $toSql = $c->toSql(false, false);
        $this->assertEquals('my condition = true', $toSql[0]);

        $c->addExpression(DB::expr('second'));
        $toSql = $c->toSql(false, false);
        $this->assertEquals('my condition = true AND second', $toSql[0]);

        $c->addAttribute(new Attribute('a', 1, '='));
        $toSql = $c->toSql(false, false);
        $this->assertEquals('`a` = ? AND my condition = true AND second', $toSql[0]);
        $this->assertEquals(array(1), $toSql[1]);
    }
}