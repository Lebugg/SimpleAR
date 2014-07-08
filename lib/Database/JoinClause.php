<?php namespace SimpleAR\Database;

class JoinClause
{
    /**
     * The name of the table to join.
     *
     * @var string
     */
    public $table;

    /**
     * The alias of the table.
     *
     * It *has* to be set during build step even if we won't use it.
     *
     * @var string
     */
    public $alias;

    /**
     * The type of join to perform.
     */
    public $type;

    /**
     * List of ON clauses for this JoinClause.
     *
     * @var array
     */
    public $ons = array();

    const LEFT  = 1;
    const RIGHT = 2;
    const INNER = 3;
    const OUTER = 4;

    public function __construct($table, $alias = '', $type = self::INNER)
    {
        $this->table = $table;
        $this->alias = $alias ?: $table;
        $this->type = $type;
    }

    /**
     * Add an ON clause to the current JoinClause.
     *
     * @param string $leftTable The left table's alias or name.
     * @param string $leftAttr  The left table's column.
     * @param string $rightTable The right table's alias or name.
     * @param string $rightAttr  The right table's column.
     * @param string $operator The operator to use.
     *
     * @return $this
     */
    public function on($leftTable, $leftAttr,
        $rightTable = '', $rightAttr = '', $operator = '=')
    {
        $rightTable = $rightTable ?: $this->alias;
        $rightAttr  = $rightAttr ?: 'id';

        $this->ons[] = array($leftTable, $leftAttr, $rightTable, $rightAttr, $operator);
        return $this;
    }
}
