<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;
use \SimpleAR\Database\Query;

class Exists extends Condition
{
    public $type = 'Exists';

    /**
     * A optional subquery (for EXISTS or IN clauses).
     *
     * @var Query
     */
    public $query;

    public function __construct(Query $q, $logicalOp = 'AND')
    {
        $this->query = $q;
        $this->logicalOp = $logicalOp;
    }
}
