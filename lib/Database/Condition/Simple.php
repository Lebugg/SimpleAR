<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;

class Simple extends Condition
{
    /**
     * The type of condition.
     */
    public $type = 'Basic';


    public $tableAlias;
    public $column;
    public $operator;
    public $value;

    public function __construct($tableAlias = '', $column = '', $operator = '', $value = '', $logicalOp = 'AND')
    {
        $this->tableAlias = $tableAlias;
        $this->column = (array) $column;
        $this->operator = $operator ?: '=';
        $this->value = $value;
        $this->logicalOp = $logicalOp;
    }
}
