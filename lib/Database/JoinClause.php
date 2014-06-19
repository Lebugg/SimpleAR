<?php namespace \Simple\Database;

class JoinClause
{
    /**
     * The name of the table to join.
     *
     * @var string
     */
    public $table;

    /**
     * The type of join to perform.
     */
    public $type;

    const LEFT  = 1;
    const RIGHT = 2;
    const INNER = 3;
    const OUTER = 4;

    public function __construct($type = self::INNER)
    {
        $this->type = $type;
    }
}
