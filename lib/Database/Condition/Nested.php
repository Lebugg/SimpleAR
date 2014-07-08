<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;

class Nested extends Condition
{
    public $type = 'Nested';
    public $nested = array();

    public function __construct(array $conditions, $logicalOp = 'AND')
    {
        $this->nested = $conditions;
        $this->logicalOp = $logicalOp;
    }
}
