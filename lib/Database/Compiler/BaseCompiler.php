<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

use \SimpleAR\Database\Query\Insert;
use \SimpleAR\Database\Query\Delete;
use \SimpleAR\Database\JoinClause;

class BaseCompiler extends Compiler
{
    public $components = array(
        'insert' => array(
            'into',
            'columns',
            'values',
        ),
        'delete' => array(
            'deleteFrom',
            'using',
            'where',
        ),
    );

    public function compileInsert(Insert $q)
    {
        $components = $this->components['insert'];
        $sql = $this->_compileComponents($q, $components);
        $sql = 'INSERT ' . $this->_concatenate($sql);

        return $sql;
    }

    public function compileDelete(Delete $q)
    {
        $components = $this->components['delete'];
        $sql = $this->_compileComponents($q, $components);
        $sql = 'DELETE ' . $this->_concatenate($sql);

        return $sql;
    }

    /**
     * Compile the INTO clause of an INSERT statement.
     *
     * @param string $tableName The name of the table to insert into.
     */
    protected function _compileInto($tableName)
    {
        $qTable = $this->wrap($tableName);

        return 'INTO ' . $qTable;
    }

    /**
     * Compile columns for INSERT query.
     *
     * @param array $columns The real columns, not the model attributes.
     * @return string
     */
    protected function _compileColumns(array $columns)
    {
        if (! $columns)
        {
            return '';
        }

        $qStr = $this->wrapArrayToString($columns);
        return '(' . $qStr . ')';
    }

    /**
     * Compile the VALUES clause of an INSERT statement.
     *
     * @param array $values The values to insert. It can be an array of values 
     * or an array of array of values in case user wants to insert several rows.
     */
    protected function _compileValues(array $values)
    {
        $sql   = '';
        $count = count($values);

        // $values is a multidimensional array. Actually, it is an array
        // of tuples.
        if (is_array($values[0]))
        {
            // Tuple cardinal.
            $tupleSize = count($values[0]);
            
            $tuple = '(' . str_repeat('?,', $tupleSize - 1) . '?)';
            $sql .= str_repeat($tuple . ',', $count - 1) . $tuple;
        }
        // Simple array.
        else
        {
            if ($count)
            {
                $sql .= str_repeat('?,', $count - 1) . '?';
            }

            $sql = '(' . $sql . ')';
        }

        return 'VALUES' . $sql;
    }

    /**
     * Compile DELETE FROM clause.
     *
     * @param string|array $from
     */
    protected function _compileDeleteFrom($from)
    {
        // DELETE can be made over several tables at the same time. Here, we 
        // don't want to cover two cases: one table or several tables. Thus, 
        // we'll work with an array, whatever the table numbers.
        $from = (array) $from;

        foreach ($from as $tableName)
        {
            $tableNames[] = $this->useTableAlias
                ? $this->_getAliasForTableName($tableName)
                : $tableName;
        }

        return 'FROM ' . $this->wrapArrayToString($tableNames);
    }

    /**
     * Compile a set of JOIN clauses.
     *
     * @param array $joins an array of JoinClause.
     */
    protected function _compileJoins(array $joins)
    {
        $sql = array();

        // A join list do not start with a "JOIN" keyword. I don't want to do 
        // string manipulation, so I use a $first flag to tell _compileJoin() 
        // whether to prepend or not the keyword.
        $first = true;
        foreach ($joins as $join)
        {
            $sql[] = $this->_compileJoin($join, !$first);
            $first = false;
        }

        $sql = $this->_concatenate($sql);

        return $sql;
    }

    /**
     * Compile a JOIN clause.
     *
     * @param JoinClause $join
     *
     */
    protected function _compileJoin(JoinClause $join, $withJoinKeyword = true)
    {
        $sql = $withJoinKeyword ? $this->_compileJoinType($join->type) . ' ' : '';

        $alias = $this->useTableAlias ? $join->alias : '';
        $sql .= $this->_compileAlias($join->table, $alias);

        if ($join->ons)
        {
            $sql .= ' ' . $this->_compileJoinOns($join->ons);
        }

        return $sql;
    }

    protected function _compileJoinType($joinType)
    {
        switch ($joinType)
        {
            case JoinClause::INNER: return 'INNER JOIN';
            case JoinClause::LEFT: return 'LEFT JOIN';
            case JoinClause::RIGHT: return 'RIGHT JOIN';
            default: return '';
        }
    }

    /**
     * Compile a list of ON clauses of a JOIN clause.
     *
     * @param array $ons An array of array of ON clauses.
     * @return string Compiled SQL.
     *
     * @see _compileJoinOn()
     */
    protected function _compileJoinOns(array $ons)
    {
        $sql = array();
        foreach ($ons as $on)
        {
            $sql[] = $this->_compileJoinOn($on);
        }

        return $this->_concatenate($sql);
    }

    /**
     * Compile an ON clause for a JOIN clause.
     *
     * @param array $on The ON array.
     * @return string Compiled SQL.
     */
    protected function _compileJoinOn(array $on)
    {
        $lt = $this->wrap($on[0]);
        $la = $this->wrap($on[1]);
        $rt = $this->wrap($on[2]);
        $ra = $this->wrap($on[3]);
        $op = $on[4];

        return "ON $lt.$la $op $rt.$ra";
    }

    /**
     * Compile USING clause of a DELETE statement.
     *
     * USING clause is just like a FROM clause of a SELECT statement. But here, 
     * we are sure to use _compileJoins().
     *
     * @param array $using A join array.
     */
    protected function _compileUsing(array $using)
    {
        return 'USING ' . $this->_compileJoins($using);
    }

    protected function _compileWhere($where)
    {
        $sql = '';

        return $sql;
    }
}
