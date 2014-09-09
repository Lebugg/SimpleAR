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
     * @param string $lAlias The left table's alias or name.
     * @param string $lCols  The left table's column.
     * @param string $rAlias The right table's alias or name.
     * @param string $rCols  The right table's column.
     * @param string $op The operator to use.
     *
     * @return $this
     */
    public function on($lAlias, $lCols,
        $rAlias = '', $rCols = ['id'], $op = '=')
    {
        $rAlias = $rAlias ?: $this->alias;
        $lCols = (array) $lCols;
        $rCols = (array) $rCols;

        foreach ($lCols as $i => $lCol)
        {
            $this->ons[] = array($lAlias, $lCol, $rAlias, $rCols[$i], $op);
        }

        return $this;
    }
}
