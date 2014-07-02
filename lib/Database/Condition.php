<?php namespace SimpleAR\Database;

require __DIR__ . '/Condition/Simple.php';
require __DIR__ . '/Condition/Exists.php';
require __DIR__ . '/Condition/Nested.php';

abstract class Condition
{
    /**
     * The type of condition.
     */
    public $type;

    /**
     * The logical operator to use to tie this condition to the previous one.
     *
     * Possible value: 'AND', 'OR'.
     *
     * @var string
     */
    public $logicalOp = 'AND';

    /**
     * Should the WHERE clause be negated?
     *
     * In SQL, conditions can be negated. If this property is set to true, the 
     * "NOT" keyword will be prepend to the condition.
     *
     * @var bool
     */
    public $not = false;
}
