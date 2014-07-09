<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\JoinClause;

class SelectBuilder extends WhereBuilder
{
    public $type = Builder::SELECT;

    public $availableOptions = array(
        'root',
        'conditions',
        'limit',
        'offset',
    );

    /**
     * Set query root.
     *
     * @override
     */
    public function root($root)
    {
        parent::root($root);

        $joinClause = new JoinClause($this->_table->name, $this->getRootAlias());
        $this->_joinClauses[$this->getRootAlias()] = $joinClause;
        //$this->_components['from'] = array($joinClause);

        return $this;
    }

    public function select(array $columns, $expand = true)
    {
        $this->_selectColumns('', $columns, $expand);
    }

            //$c['columns'] = array($this->getRootAlias() => array('columns' => array('*')));
    /**
     * Add an aggregate function to the query (count, avg, sum...).
     *
     * Aggregates do not replace columns selection. Both can be combined.
     *
     * @param string $fn The aggregate function.
     * @param string $attribute The attribute to apply the function on.
     * @param string $alias The result alias to give to this aggregate.
     */
    public function aggregate($function, $attribute = '*', $resultAlias = '')
    {
        list($tableAlias, $columns) = $attribute === '*'
            ? array('', array('*'))
            : $this->_processExtendedAttribute($attribute)
            ;

        $this->_components['aggregates'][] = compact('columns', 'function',
            'tableAlias', 'resultAlias');
    }

    /**
     * Add a count aggregate to the query.
     *
     * @param array|string $columns The columns to count on.
     */
    public function count($columns = '*', $resultAlias = '')
    {
        $this->aggregate('COUNT', $columns, $resultAlias);
    }

    public function orderBy($attribute, $sort = 'ASC')
    {
        list($tableAlias, $columns) = $this->_processExtendedAttribute($attribute);
        $column = $columns[0];

        $this->_components['orderBy'][] = compact('tableAlias', 'column', 'sort');
    }

    public function groupBy($attribute)
    {
        list($tableAlias, $columns) = $this->_processExtendedAttribute($attribute);
        $column = $columns[0];

        $this->_components['groupBy'][] = compact('tableAlias', 'column');
    }

    public function limit($limit, $offset = null)
    {
        $this->_components['limit'] = (int) $limit;

        $offset === null || $this->offset($offset);
    }

    public function offset($offset)
    {
        $this->_components['offset'] = (int) $offset;
    }

    /**
     * Eager load another table.
     *
     * If if you give a deep relation to eager load, columns of every middle 
     * tables will be selected. For example, if you do:
     *
     *  `$builder->with('articles/author')`
     *
     * authors' *and* articles' columns will be selected.
     *
     * @param string $relation The model relation name relative to the root
     * model.
     */
    public function with($relation)
    {
        // We have two things to do:
        //  1) Include related table;
        //  2) Select related table columns.

        // 1) We have to call addInvolvedTable() function. It takes a table alias
        // as a parameter.
        $tableAlias = $this->relationsToTableAlias($relation);
        $this->addInvolvedTable($tableAlias, JoinClause::LEFT);
        //$this->_components['from'][] = $this->getJoinClause($tableAlias);

        // 2)
        $alias = '';
        foreach (explode('.', $tableAlias) as $relName)
        {
            $alias .= $alias ? '.' . $relName : $relName;
            $this->_selectColumns($alias, array('*'));
        }

        return $this;
    }

    /**
     * Add columns to the list of to-be-selected columns.
     *
     * @param string $tableAlias The alias of the table the columns belong.
     * @param array  $columns The columns to select. (Columns, not attributes).
     * @param bool   $expand Whether to expand '*' wildcard or not.
     */
    protected function _selectColumns($tableAlias, array $columns, $expand = true)
    {
        if ($expand && $columns === array('*'))
        {
            $columns = $this->getInvolvedTable($tableAlias)->getColumns();
            // Compiler wants it the other way.
            $columns = array_flip($columns);
        }

        $this->_components['columns'][$tableAlias]['columns'] = $columns;
    }

    protected function _onAfterBuild()
    {
        $c =& $this->_components;
        $rootAlias = $this->getRootAlias();

        if (empty($c['columns'][$rootAlias])
            && empty($c['columns'][''])
            && empty($c['aggregates'])
        ) {
            $this->_selectColumns($rootAlias, array('*'));
        }

        $c['from'] = array_values($this->_joinClauses);
    }

}
