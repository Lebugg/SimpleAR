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

    protected function _onAfterBuild()
    {
        $c =& $this->_components;
        if (empty($c['columns']) && empty($c['aggregates']))
        {
            $c['columns'] = array('' => array('columns' => array('*')));
        }
    }

    public function root($root)
    {
        parent::root($root);

        $joinClause = new JoinClause($this->_table->name, '');
        $this->_components['from'] = array($joinClause);
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

    protected function _buildLimit($limit)
    {
        $this->_components['limit'] = (int) $limit;
    }

    protected function _buildOffset($offset)
    {
        $this->_components['offset'] = (int) $offset;
    }
}
