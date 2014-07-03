<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

use \SimpleAR\Database\Query;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Condition as WhereClause;

/**
 * This is the base SQL compiler.
 *
 * It is not aimed to work with any particular DBMS.
 *
 * It is greatly inspired from Eloquent ORM from the Laravel framework.
 * @see https://github.com/illuminate/database/blob/master/Query/Grammars/Grammar.php
 *
 * @author Lebugg
 */
class BaseCompiler extends Compiler
{
    public $components = array(
        'insert' => array(
            'into',
            'insertColumns',
            'values',
        ),
        'select' => array(
            'columns',
            'aggregates',
            'from',
            'where',
            'orderBy',
            'limit',
            'offset',
        ),
        'update' => array(
            'updateFrom',
            'set',
            'where',
        ),
        'delete' => array(
            // "deleteFrom" component can be a string or an array of strings.
            'deleteFrom',
            'using',
            'where',
        ),
    );

    /**
     * Available operator for this compiler.
     *
     * @var array
     */
    public $operators = array(
        '=', '<=', '>=', '!=',
        'IN', 'BETWEEN',
        'LIKE',
    );

    /**
     * Possible arrayfications of some operators.
     *
     * @var array
     */
    public $operatorsArrayfy = array(
        '=' => 'IN',
        '!=' => 'NOT IN',
    );

    public function compileInsert(Query $q)
    {
        $components = $this->components['insert'];
        $sql = $this->_compileComponents($q, $components);
        $sql = 'INSERT ' . $this->_concatenate($sql);

        return $sql;
    }

    public function compileSelect(Query $q)
    {
        $components = $this->components['select'];

        // If we are constructing query over several tables, we should use
        // table aliases.
        if (count($q->components['from']) > 1)
        {
            $this->useTableAlias = true;
        }

        $sql = $this->_compileComponents($q, $components);
        $sql = 'SELECT ' . $this->_concatenate($sql);

        return $sql;
    }

    public function compileUpdate(Query $q)
    {
        // If we are constructing query over several tables, we should use
        // table aliases.
        if (count($q->components['updateFrom']) > 1)
        {
            $this->useTableAlias = true;
        }

        $components = $this->components['update'];
        $sql = $this->_compileComponents($q, $components);
        $sql = 'UPDATE ' . $this->_concatenate($sql);

        return $sql;
    }

    public function compileDelete(Query $q)
    {
        // If we are constructing query over several tables, we should use
        // table aliases.
        if (! empty($q->components['using']))
        {
            $this->useTableAlias = true;
        }

        $components = $this->components['delete'];
        $sql = $this->_compileComponents($q, $components);
        $sql = 'DELETE ' . $this->_concatenate($sql);

        return $sql;
    }

    public function compileWhere(Query $q)
    {
        return $this->_compileWhere($q->components['where']);
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
    protected function _compileInsertColumns(array $columns)
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
     * Compile aggregates clauses.
     *
     * @param array $aggregates The aggregates to compile.
     *
     * $aggregates is an array of array. Each sub-array correspond to an 
     * aggregate to build. Each has these entries:
     *  
     *  * "function": The aggregate function;
     *  * "columns": The columns to apply the function on.
     */
    protected function _compileAggregates(array $aggregates)
    {
        $sql = array();
        foreach ($aggregates as $agg)
        {
            // $cols is now a string of wrapped column names.
            $cols = $this->columnize($agg['columns'], $agg['tableAlias']);
            $fn   = $agg['function'];

            $sql[] = $fn . '(' . $cols . ')' . $this->_compileAs($agg['resultAlias']);
        }

        return implode(',', $sql);
    }

    protected function _compileColumns(array $columns)
    {
        $sql = array();
        foreach ($columns as $tableAlias => $data)
        {
            $columns     = $data['columns'];
            $tableAlias  = $this->useTableAlias ? $tableAlias : '';
            $resultAlias = $this->useResultAlias ? $data['resultAlias'] : '';

            $sql[] = $this->columnize($columns, $tableAlias, $resultAlias);
        }

        return implode(',', $sql);
    }

    protected function _compileFrom($from)
    {
        return 'FROM ' . $this->_compileJoins($from);
    }

    protected function _compileOrderBy($orderBys)
    {
        foreach ($orderBys as $item)
        {
            $col = $this->column($item['column'], $item['tableAlias']);
            $sql[] = $col . ' ' . $item['sort'];
        }

        return 'ORDER BY ' . implode(',', $sql);
    }

    protected function _compileLimit($limit)
    {
        return 'LIMIT ' . $limit;
    }

    protected function _compileOffset($offset)
    {
        return 'OFFSET ' . $offset;
    }

    /**
     * Compile DELETE FROM clause.
     *
     * @param string|array $from An array of table names or table aliases.
     */
    protected function _compileDeleteFrom($from)
    {
        // DELETE can be made over several tables at the same time. Here, we 
        // don't want to cover two cases: one table or several tables. Thus, 
        // we'll work with an array, whatever the table numbers.
        $tableNames = (array) $from;

        return 'FROM ' . $this->wrapArrayToString($tableNames);
    }

    /**
     * There is no "FROM" keywprd in UPDATE statements. However, table 
     * declaration is the same as in SELECT.
     *
     * @param array $from An array of JoinClause.
     * @return string
     */
    protected function _compileUpdateFrom($from)
    {
        return $this->_compileJoins($from);
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
     * @return string
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

    /**
     * Return SQL keywords matching the given join type.
     *
     * @param int $joinType The join type. {@see SimpleAR\Database\JoinClause}
     * @return string
     */
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

    protected function _compileWhere(array $wheres)
    {
        return 'WHERE ' . $this->_compileConditions($wheres);
    }

    /**
     * Compile a bunch of WHERE clauses.
     *
     * @param array $wheres
     * @return string
     */
    protected function _compileConditions(array $wheres)
    {
        $sql = '';
        foreach ($wheres as $where)
        {
            $sql .= ' ' . $this->_compileCondition($where);
        }

        // We have to remove the first "AND" or "OR" at the beginning of the 
        // string.
        $sql = preg_replace('/ AND | OR /', '', $sql, 1);

        return $sql;
    }

    /**
     * Compile a WHERE clause.
     *
     * @param WhereClause $where
     * @return string SQL
     */
    protected function _compileCondition(WhereClause $where)
    {
        $fn  = '_where' . $where->type;
        $sql = $this->$fn($where);

        $logicalOp = $where->logicalOp;
        $not       = $where->not ? ' NOT ' : ' ';

        $sql = $logicalOp . $not . $sql;
        return $sql;
    }

    /**
     * Compile a basic WHERE clause.
     *
     * "Basic" means this condition format: <column> <operator> <value>
     *
     * @param WhereClause $where
     * @return SQL
     */
    protected function _whereBasic(WhereClause $where)
    {
        $col = $this->_compileWhereColumns($where);
        $op  = $this->_getWhereOperator($where);
        $val = $this->parameterize($where->value);

        return "$col $op $val";
    }

    /**
     * Compile an EXISTS clause.
     *
     * @param Condition\Exists $where
     * @return string SQL
     */
    protected function _whereExists(WhereClause $where)
    {
        return 'EXISTS (' . $this->compileSelect($where->query) . ')';
    }

    /**
     * Compile an nested where clause.
     *
     * @param Condition\Nested $where
     * @return string SQL
     */
    protected function _whereNested(WhereClause $where)
    {
        return '(' . $this->_compileConditions($where->nested) . ')';
    }

    /**
     * Compile a "column" portion of a WHERE clause.
     *
     * If we are using table aliases, table alias will be prepend to it.
     * Result string is wrapped SQL.
     *
     * It handles one or several columns.
     *
     * @param WhereClause $where
     * @return Safe SQL.
     */
    protected function _compileWhereColumns(WhereClause $where)
    {
        $alias = $this->useTableAlias ? $where->tableAlias : '';

        return $this->columnize($where->column, $alias);
    }

    /**
     * Get appropriate operator for the given WhereClause.
     *
     * Sometimes, the conditional operator the user chose is not fitting for the 
     * values type. Thus, we need to use a more appropriate one.
     *
     * Example:
     * --------
     *
     * If this condition is given:
     *
     *  ['beerProof', '=', [7, 8, 9]] // A beer of which proof is 7%, 8% or 9%.
     *
     * We cannot use '=' operator, but 'IN' operator.
     *
     * @param WhereClause $where
     */
    protected function _getWhereOperator(WhereClause $where)
    {
        $op = $where->operator;
        $val = $where->value;

        // Do we need to arrayfy this operator?
        if (is_array($val) && isset($this->operatorsArrayfy[$op]))
        {
            $op = $this->operatorsArrayfy[$op];
        }

        return $op;
    }

    /**
     * Compile a SET clause.
     *
     * @param array $sets An array of "set" array.
     * @return string a SET clause.
     *
     * @see _compileSet()
     */
    protected function _compileSet(array $sets)
    {
        foreach ($sets as $set)
        {
            $sql[] = $this->_compileSetPart($set);
        }

        $sql = 'SET ' . implode(',', $sql);
        return $sql;
    }

    /**
     * Compile a part of a SET clause.
     *
     * $set array entries:
     *
     *  * 'tableAlias': The table alias to use.
     *  * 'column': The column.
     *  * 'value': The value.
     *
     * @param array $set The set clause part data.
     * @return string SQL
     */
    protected function _compileSetPart(array $set)
    {
        $tableAlias = $this->useTableAlias ? $set['tableAlias'] : '';
        $left  = $this->column($set['column'], $tableAlias);
        $right = $this->parameterize($set['value']);

        return $left . ' = ' . $right;
    }
}
