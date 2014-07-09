<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;
use \SimpleAR\Database\Query;

class SubQuery extends Condition
{
    public $type = 'Sub';

    /**
     * A optional subquery (for EXISTS or IN clauses).
     *
     * @var Query
     */
    public $query;
    public $operator;
    public $value;

    public function __construct(Query $q, $op, $value, $logicalOp = 'AND')
    {
        $this->query = $q;
        $this->operator = $op;
        $this->value = $value;
        $this->logicalOp = $logicalOp;
    }
}
