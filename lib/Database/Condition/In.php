<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;
use \SimpleAR\Database\Query;

class In extends Condition
{
    public $type = 'In';

    /**
     * A optional subquery (for EXISTS or IN clauses).
     *
     * @var Query
     */
    public $query;

    public $tableAlias;
    public $column;

    /**
     * IN value list.
     *
     * @var mixed
     */
    public $value;

    public function __construct($tableAlias = '', $column = '', $value, $logicalOp = 'AND')
    {
        $this->tableAlias = $tableAlias;
        $this->column = (array) $column;
        $this->value = $value;
        $this->logicalOp = $logicalOp;
    }
}
