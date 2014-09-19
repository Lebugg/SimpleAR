<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\Expression;
use \SimpleAR\Database\JoinClause;
use \SimpleAR\Database\Query;

class SelectBuilder extends WhereBuilder
{
    public $type = Builder::SELECT;

    /**
     * @override
     */
    public function root($root, $alias = null)
    {
        parent::root($root, $alias);

        $table = $this->_table->name;
        $alias = $alias ?: $this->getRootAlias();
        $this->setJoinClause($alias, new JoinClause($table, $alias));

        return $this;
    }

    /**
     * Add attributes to select.
     *
     * Note: Table primary key will always be added to the selection.
     * -----
     *
     * @param array|Expression $attributes An attribute array or an Expression 
     * object.
     * @param bool  $expand  Whether to expand '*' wildcard.
     */
    public function select($attributes, $expand = true)
    {
        if ($attributes instanceof Expression)
        {
            $this->_selectColumn($attributes, ''); return;
        }

        if ($attributes !== array('*'))
        {
            // We add primary key if not present.
            $table = $this->getRootTable();
            $attributes = array_unique(array_merge($table->getPrimaryKey(), $attributes));
            $columns = $this->convertAttributesToColumns($attributes, $table);
            $attributes = array_combine($attributes, $columns);
        }

        $this->_selectColumns($this->getRootAlias(), $attributes, $expand);
    }

    /**
     * Alias for select().
     */
    public function get($attributes, $expand = true)
    {
        $this->select($attributes, $expand);
    }

    /**
     * Select result of a sub-query.
     *
     * @param Query  $sub The query of which to select result.
     * @param string $alias The alias to give to the sub-result.
     */
    public function selectSub(Query $sub, $alias)
    {
        $this->addValueToQuery($sub->getValues());

        $subSql = new Expression('(' . $sub->getSql() . ')');
        $this->_selectColumn($subSql, $alias);
    }

    /**
     * Add an aggregate function to the query (count, avg, sum...).
     *
     * Two behaviours:
     * ---------------
     *
     * 1) Add an aggregate to the selection list (with other columns or
     * aggregates). Query won't be executed.
     * 2) Set the aggregate as the only item to fetch. Query will be executed
     * and first value will be returned.
     *
     * The behaviour choice depends on $resAlias parameter. If null, 2) will 
     * be used, 1) otherwise.
     * You still can choose one behaviour over another using `addAggregate()` 
     * and `setAggregate()` methods.
     *
     * @param string $fn The aggregate function.
     * @param string $attribute The attribute to apply the function on.
     * @param string $alias The result alias to give to the aggregate.
     */
    public function aggregate($fn, $attribute = '*', $resAlias = null)
    {
        return $resAlias
            ? $this->addAggregate($fn, $attribute, $resAlias)
            : $this->setAggregate($fn, $attribute);
    }

    public function addAggregate($fn, $attribute = '*', $resAlias = '')
    {
        list($tAlias, $cols) = $attribute === '*'
            ? array('', array('*'))
            : $this->_processAttribute($attribute);

        $this->_components['aggregates'][] = compact('cols', 'fn', 'tAlias', 'resAlias');
    }

    public function setAggregate($fn, $attribute = '*')
    {
        list($tAlias, $cols) = $attribute === '*'
            ? array('', array('*'))
            : $this->_processAttribute($attribute);

        $this->_components['aggregates'] = array(compact('cols', 'fn', 'tAlias'));
        unset($this->_components['columns']);
    }

    /**
     * Add a count aggregate to the query.
     *
     * @param array|string $columns The columns to count on.
     */
    public function count($columns = '*', $resAlias = null)
    {
        $this->aggregate('COUNT', $columns, $resAlias);
    }

    public function sum($columns = '*', $resAlias = null)
    {
        $this->aggregate('SUM', $columns, $resAlias);
    }

    public function max($columns = '*', $resAlias = null)
    {
        $this->aggregate('MAX', $columns, $resAlias);
    }

    public function min($columns = '*', $resAlias = null)
    {
        $this->aggregate('MIN', $columns, $resAlias);
    }

    public function avg($columns = '*', $resAlias = null)
    {
        $this->aggregate('AVG', $columns, $resAlias);
    }

    public function orderBy($attribute, $sort = 'ASC')
    {
        if (is_array($attribute))
        {
            foreach ($attribute as $attr => $sort) {
                if (is_string($attr))
                {
                    $this->orderBy($attr, $sort);
                }
                else
                {
                    // User did not specify the sort order. $sort contains the 
                    // attribute.
                    $this->orderBy($sort);
                }
            }

            return;
        }

        list($tAlias, $columns) = $this->_processExtendedAttribute($attribute);
        $column = $columns[0];

        $this->_components['orderBy'][] = compact('tAlias', 'column', 'sort');
    }

    public function groupBy($attribute)
    {
        foreach ((array) $attribute as $attr)
        {
            list($tAlias, $columns) = $this->_processExtendedAttribute($attr);
            $column = $columns[0];

            $this->_components['groupBy'][] = compact('tAlias', 'column');
        }
    }

    /**
     * Set the limit number of objects to return.
     *
     * @param int $limit The limit
     * @param int $offset The offset (Optional).
     */
    public function limit($limit, $offset = null)
    {
        $this->_components['limit'] = (int) $limit;

        $offset === null || $this->offset($offset);
    }

    /**
     * Set the offset of the query.
     *
     * @param int $offset The offset to set.
     */
    public function offset($offset)
    {
        $this->_components['offset'] = (int) $offset;
    }

    /**
     * Eager load another table.
     *
     * If you give a deep relation to eager load, columns of every middle
     * tables will be selected. For example, if you do:
     *
     *  `$builder->with('articles/author')`
     *
     * authors' *and* articles' columns will be selected.
     *
     * @param string|array $relation The model relation name relative to the root
     * model *or* an array of relation names.
     */
    public function with($relation)
    {
        // Handle multiple relations.
        if (is_array($relation))
        {
            array_walk($relation, array($this, 'with'));
            return $this;
        }

        // We have two things to do:
        //  1) Include related table;
        //  2) Select related table columns.

        // 1) We have to call addInvolvedTable() function. It takes a table alias
        // as a parameter.
        $tAlias = $this->relationsToTableAlias($relation);
        $this->addInvolvedTable($tAlias, JoinClause::LEFT);

        // 2)
        $alias = '';
        foreach (explode('.', $tAlias) as $relName)
        {
            $alias .= $alias ? '.' . $relName : $relName;
            $this->_selectColumns($alias, array('*'));
        }

        return $this;
    }

    /**
     * Add columns to the list of to-be-selected columns.
     *
     * @param string $tAlias The alias of the table the columns belong.
     * @param array  $columns The columns to select. (Columns, not attributes).
     * @param bool   $expand Whether to expand '*' wildcard or not.
     */
    protected function _selectColumns($tAlias, array $columns, $expand = true)
    {
        if ($expand && $columns === array('*'))
        {
            $columns = $this->getInvolvedTable($tAlias)->getColumns();
        }

        $resAlias = $tAlias === $this->getRootAlias() ? '' : $tAlias;
        $this->_components['columns'][$tAlias] = array(
            'columns' => $columns,
            'resAlias' => $resAlias
        );
    }

    /**
     * Add a column to select.
     *
     * @param string $columns The column to select.
     * @param string $alias The returned column name.
     */
    protected function _selectColumn($column, $alias)
    {
        $this->_components['columns'][] = array(
            'column' => $column,
            'alias' => $alias,
        );
    }

    protected function _buildOrder_by($orderBy)
    {
        $this->orderBy($orderBy);
    }

    protected function _buildGroup_by($groupBy)
    {
        $this->groupBy($groupBy);
    }

    protected function _onAfterBuild()
    {
        $this->_components['from'] = array_values($this->_joinClauses);
    }
}
