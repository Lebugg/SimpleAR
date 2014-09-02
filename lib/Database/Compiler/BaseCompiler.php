<?php namespace SimpleAR\Database\Compiler;

use \SimpleAR\Database\Compiler;

use \SimpleAR\Database\Query;
use \SimpleAR\Database\JoinClause;
//use \SimpleAR\Database\Condition as WhereClause;

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
            'groupBy',
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

    public function compileInsert(array $components)
    {
        $this->useTableAlias = false;

        $availableComponents = $this->components['insert'];
        $sql = $this->_compileComponents($availableComponents, $components);
        $sql = 'INSERT ' . implode(' ', $sql);

        return $sql;
    }

    public function compileSelect(array $components)
    {
        $availableComponents = $this->components['select'];

        // If we are constructing query over several tables, we should use
        // table aliases.
        if (count($components['from']) > 1)
        {
            $this->useTableAlias = true;
        }

        $sql = $this->_compileComponents($availableComponents, $components);
        $sql = 'SELECT ' . implode(' ', $sql);

        return $sql;
    }

    public function compileUpdate(array $components)
    {
        // If we are constructing query over several tables, we should use
        // table aliases.
        $this->useTableAlias = count($components['updateFrom']) > 1;

        $availableComponents = $this->components['update'];
        $sql = $this->_compileComponents($availableComponents, $components);
        $sql = 'UPDATE ' . implode(' ', $sql);

        return $sql;
    }

    public function compileDelete(array $components)
    {
        // If we are constructing query over several tables, we should use
        // table aliases.
        $this->useTableAlias = ! empty($components['using']);

        $availableComponents = $this->components['delete'];
        $sql = $this->_compileComponents($availableComponents, $components);
        $sql = 'DELETE ' . implode(' ', $sql);

        return $sql;
    }

    public function compileWhere(array $components)
    {
        return $this->_compileWhere($components['where']);
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
     *  * "columns": The columns to apply the function on;
     *  * "tableAlias": The table alias;
     *  * "resultAlias": The aggregate column's alias.
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

        $sql = implode(',', $sql);

        // If there are simple columns to be selected too, we need to add a 
        // separation comma.
        if (isset($this->_componentsToCompile['columns'])) { $sql = ',' . $sql; }

        return $sql;
    }

    /**
     * Compile columns to select.
     *
     * @param array $columns The columns to select.
     */
    protected function _compileColumns(array $columns)
    {
        $sql = array();
        foreach ($columns as $tableAlias => $data)
        {
            if (is_string($tableAlias))
            {
                $columns     = $data['columns'];
                $tableAlias  = $this->useTableAlias ? $tableAlias : '';
                $resultAlias = isset($data['resultAlias']) ? $data['resultAlias'] : '';

                $sql[] = $this->project($columns, $tableAlias, $resultAlias);
            }

            else
            {
                $sql[] = $this->column($data['column'], '', $data['alias']);
            }
        }

        return implode(',', $sql);
    }

    /**
     * Compile a projection.
     *
     * It may be necessary to add alias to columns:
     * - we want to prefix columns with table aliases;
     * - we want to rename columns in query result (only for Select queries).
     *
     * @param array $columns The column array. It can take three forms:
     * - an indexed array where values are column names;
     * - an associative array where keys are attributes names and values are
     * columns names (for column renaming in result. Select queries only);
     * - a mix of both. Values of numeric entries will be taken as column names.
     *
     * @param array  $columns
     * @param string $tablePrefix  The table alias to prefix the column with.
     * @param string $resultPrefix The result alias to rename the column into.
     *
     * @return string SQL
     */
    public function project(array $columns, $tablePrefix = '', $resultPrefix = '')
    {
        $tablePrefix  = $tablePrefix ? $this->wrap($tablePrefix) . '.' : '';
        $resultPrefix = $resultPrefix ? $resultPrefix . '.' : '';

        $cols = array();
        foreach($columns as $attribute => $column)
        {
            $left = $right = '';

            if (is_string($attribute))
            {
                $left  = $tablePrefix . $this->wrap($column);
                $right = $this->wrap($resultPrefix . $attribute);
            }
            else
            {
                $left = $tablePrefix . $this->wrap($column);
                if ($resultPrefix)
                {
                    $right = $this->wrap($resultPrefix . $column);
                }
            }

            $cols[] = $right ? $left . ' AS ' . $right : $left;
        }

        return implode(',', $cols);
    }

    /**
     * Compile a FROM clause of a SELECT statement.
     *
     * @param array $from An array of JoinClause.
     * @return string SQL
     */
    protected function _compileFrom($from)
    {
        return 'FROM ' . $this->_compileJoins($from);
    }

    /**
     * Compile an ORDER BY clause.
     *
     * @param array $orderBys A list of attributes to order on.
     * @return string SQL
     */
    protected function _compileOrderBy(array $orderBys)
    {
        foreach ($orderBys as $item)
        {
            $tableAlias = $this->useTableAlias ? $item['tableAlias'] : '';
            $col = $this->column($item['column'], $tableAlias);
            $sql[] = $col . ' ' . $item['sort'];
        }

        return 'ORDER BY ' . implode(',', $sql);
    }

    /**
     * Compile the GROUP BY clause of a SELECT statement.
     *
     * @param array $groups A list of columns to group on.
     * @return string SQL
     */
    protected function _compileGroupBy(array $groups)
    {
        foreach ($groups as $g)
        {
            $tableAlias = $this->useTableAlias ? $g['tableAlias'] : '';
            $sql[] = $this->column($g['column'], $tableAlias);
        }

        return 'GROUP BY ' . implode(',', $sql);
    }

    /**
     * Compile a LIMIT clause of a SELECT statement.
     *
     * @param int $limit The limit.
     * @return string SQL
     */
    protected function _compileLimit($limit)
    {
        return 'LIMIT ' . $limit;
    }

    /**
     * Compile the OFFSET clause of a SELECT statement.
     *
     * @param int $offset The offset.
     * @return string SQL
     */
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

        $sql = implode(' ', $sql);

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

        return implode(' ', $sql);
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

    /**
     * Compile a complete WHERE clause.
     *
     * @param array $wheres An array of conditions.
     * @return string SQL
     */
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
     * @param array $where
     * @return string SQL
     */
    protected function _compileCondition(array $where)
    {
        $not = empty($where['not']) ? '' : 'Not';
        $fn  = '_where' . $not . $where['type'];
        $sql = $this->$fn($where);

        $logicalOp = $where['logic'] ?: 'AND';

        $sql = $logicalOp . ' ' . $sql;
        return $sql;
    }

    /**
     * Compile a basic WHERE clause.
     *
     * "Basic" means this condition format: <column> <operator> <value>
     *
     * @param array $where
     * @return SQL
     */
    protected function _whereBasic(array $where)
    {
        if ($where['val'] === null)
        {
            return $this->_whereIsNull($where);
        }

        $alias = $this->useTableAlias ? $where['table'] : '';
        $col = $this->columnize($where['cols'], $alias);
        $op  = $this->_getWhereOperator($where);
        $val = $this->parameterize($where['val']);

        return "$col $op $val";
    }

    protected function _whereNotBasic(array $where)
    {
        return 'NOT ' . $this->_whereBasic($where);
    }

    /**
     * Compile an IS NULL clause.
     *
     * @param array $where
     * @return SQL
     */
    protected function _whereNull(array $where)
    {
        $alias = $this->useTableAlias ? $where['table'] : '';
        $col = $this->columnize($where['cols'], $alias);

        return "$col IS NULL";
    }

    /**
     * Compile an IS NOT NULL clause.
     *
     * @param array $where
     * @return SQL
     */
    protected function _whereNotNull(array $where)
    {
        $alias = $this->useTableAlias ? $where['table'] : '';
        $col = $this->columnize($where['cols'], $alias);

        return "$col IS NOT NULL";
    }

    protected function _whereRaw(array $where)
    {
        return $this->parameterize($where['val']);
    }

    /**
     * Compile a WHERE clause over two columns.
     *
     * "Attribute" means this condition format: <column> <operator> <column>
     *
     * @param array $where
     * @return SQL
     */
    protected function _whereAttribute(array $where)
    {
        $lCol = $this->columnize($where['lCols'], $this->useTableAlias ? $where['lTable'] : '');
        $rCol = $this->columnize($where['rCols'], $this->useTableAlias ? $where['rTable'] : '');
        $op  = $this->_getWhereOperator($where);

        return "$lCol $op $rCol";
    }

    protected function _whereNotAttribute(array $where)
    {
        return 'NOT ' . $this->_whereAttribute($where);
    }

    /**
     * Compile an EXISTS clause.
     *
     * @param array $where
     * @return string SQL
     */
    protected function _whereExists(array $where)
    {
        return 'EXISTS (' . $this->compileSelect($where['query']->getComponents()) . ')';
    }

    /**
     * Compile an EXISTS clause.
     *
     * @param array $where
     * @return string SQL
     */
    protected function _whereNotExists(array $where)
    {
        return 'NOT ' . $this->_whereExists($where);
    }

    /**
     * Compile an IN clause.
     *
     * The condition value can be the following:
     *
     *  * A Query instance to compile an IN `(<subquery>)` clause;
     *  * An array of values;
     *  * A falsy value. In this case, condition is replaced with `TRUE` or `FALSE`.
     *
     * The falsy value handling brings the following behaviours:
     *
     *  * `IN ()` means false;
     *  * `NOT IN ()` means true.
     *
     * This is arbitrary behaviour, since IN empty array is not allowed by SQL 
     * standard. See these discussions for further thoughts on it:
     *
     * @see http://bugs.mysql.com/bug.php?id=12474
     * @see http://stackoverflow.com/a/12946338/2119117
     * @see http://forumsarchive.laravel.io/viewtopic.php?id=3252
     *
     * @param array $where
     * @return string SQL
     */
    protected function _whereIn(array $where)
    {
        // If empty value is passed, we replace condition as said
        // in flower box.
        if (! $where['val']) { return 'FALSE'; }

        $sql = $where['val'] instanceof Query
            ? '(' . $this->compileSelect($where['val']) . ')'
            : $this->parameterize($where['val']);

        $alias = $this->useTableAlias ? $where['table'] : '';
        $col   = $this->columnize($where['cols'], $alias, '');

        if (isset($where['cols'][1]))
        {
            $col = '(' . $col . ')';
        }

        return "$col IN $sql";
    }

    protected function _whereNotIn(array $where)
    {
        // If empty value is passed, we replace condition as said
        // in flower box.
        if (! $where['val']) { return 'TRUE'; }

        $sql = $where['val'] instanceof Query
            ? '(' . $this->compileSelect($where['val']) . ')'
            : $this->parameterize($where['val']);

        $alias = $this->useTableAlias ? $where['table'] : '';
        $col   = $this->columnize($where['cols'], $alias, '');

        if (isset($where['cols'][1]))
        {
            $col = '(' . $col . ')';
        }

        return "$col NOT IN $sql";
    }

    protected function _whereTuple(array $where)
    {
        return $this->_whereIn($where);
    }

    protected function _whereNotTuple(array $where)
    {
        return $this->_whereNotIn($where);
    }

    protected function _whereSub(array $where)
    {
        $sub = '(' . $this->compileSelect($where['query']) . ')';
        $op  = $this->_getWhereOperator($where);
        $val = $this->parameterize($where['val']);

        return "$sub $op $val";
    }

    protected function _whereNotSub(array $where)
    {
        return 'NOT ' . $this->_whereSub($where);
    }

    /**
     * Compile an nested where clause.
     *
     * @param array $where
     * @return string SQL
     */
    protected function _whereNested(array $where)
    {
        return '(' . $this->_compileConditions($where['nested']) . ')';
    }

    /**
     * Compile an nested where clause in negative form.
     *
     * @param array $where
     * @return string SQL
     */
    protected function _whereNotNested(array $where)
    {
        return 'NOT ' . $this->_whereNested($where);
    }

    /**
     * Get appropriate operator for the given condition.
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
     * @param array $where
     */
    protected function _getWhereOperator(array $where)
    {
        $op = $where['op'];

        if (! isset($where['val']))
        {
            return $op;
        }

        $val = $where['val'];

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
        $prefix = $this->useTableAlias ? $set['tableAlias'] : '';
        $left  = $this->column($set['column'], $prefix);
        $right = $this->parameterize($set['value']);

        return $left . ' = ' . $right;
    }
}
