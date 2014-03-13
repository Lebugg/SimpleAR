<?php

use \SimpleAR\Database\Expression;
use \SimpleAR\Query\Option\Conditions;
use \SimpleAR\Query\Condition as Condition;
use \SimpleAR\Table;

class ConditionsTest extends PHPUnit_Framework_TestCase
{
    public function testBuildConditionMethod()
    {
        $opt = new Conditions(array(
            'articles/author' => 'Joe',
        ));

        $opt->build(true, 'Blog');
    }
}
