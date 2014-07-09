<?php namespace SimpleAR\Database\Condition;

use \SimpleAR\Database\Condition;

/**
 * This class modelizes a condition over two columns.
 *
 * @author Lebugg
 */
class Attribute extends Condition
{
    /**
     * The type of condition.
     */
    public $type = 'Attribute';

    public $lAlias;
    public $lCol;
    public $operator;
    public $rAlias;
    public $rCol;

    public function __construct($lAlias, $lCol, $op, $rAlias, $rCol, $logicalOp = 'AND')
    {
        $this->lAlias    = $lAlias;
        $this->lCol      = (array) $lCol;
        $this->operator  = $op;
        $this->rAlias    = $rAlias;
        $this->rCol      = (array) $rCol;
        $this->logicalOp = $logicalOp;
    }
}
