<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

use \SimpleAR\Database\Query\Insert;
use \SimpleAR\Database\Query\Delete;

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
        if (is_array($from))
        {
            $sql = $this->_compileJoins($from);
        }
        else
        {
            $alias = $this->useTableAlias ? $this->_getAliasForTableName($from) : '';
            $sql = $this->_compileAlias($from, $alias);
        }

        return 'FROM ' . $sql;
    }

    /**
     * Compile a set of JOIN clauses.
     *
     * @param array $joins an array of JoinClause.
     */
    protected function _compileJoins(array $joins)
    {
        $sql = array();
        foreach ($joins as $join)
        {
            $sql[] = $this->_compileJoin($join);
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
    protected function _compileJoin(JoinClause $join)
    {
        $sql = $this->_compileJoinType($join->type);
        $sql .= ' ' .  $this->_compileAlias($join->table, $join->alias, $this->useTableAlias);

        return $sql;
    }

    protected function _compileJoinType($joinType)
    {
        switch ($joinType)
        {
            case JoinClause::INNER: return 'INNER JOIN';
            case JoinClause::LEFT: return 'LEFT JOIN';
            case JoinClause::RIGHT: return 'RIGHT JOIN';
        }
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
