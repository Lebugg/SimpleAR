<?php namespace SimpleAR\Query;
/**
 * This file contains the Select class.
 *
 * @author Lebugg
 */

use \SimpleAR\Facades\DB;
use \SimpleAR\Query\Arborescence as Arbo;

/**
 * This class handles SELECT statements.
 */
class Select extends Where
{
    protected $_useResultAlias  = false;

    /**
     * Contains all attributes to fetch.
     *
     * @var array
     */
    protected $_distinct = false;
    protected $_columns  = array();
    protected $_aggregates;
    protected $_filter;
    protected $_from = true;
	protected $_orders = array();
    protected $_limit;
    protected $_offset;

    private $_pendingRow = false;

    protected static $_availableOptions = array('conditions', 'filter', 'group',
            'has', 'limit', 'offset', 'order', 'with');

    /**
     * The components of the query.
     *
     * They will be compiled in order of apparition in this array.
     * The order is important!
     *
     * @var array
     */
    protected static $_components = array(
        'columns',
        'aggregates',
        'from',
        'where',
        'groups',
        'orders',
        'havings',
        'limit',
        'offset',
    );

    /**
     * Return all fetch Model instances.
     *
     * This function is just a foreach wrapper around `row()`.
     *
     * @see Select::row()
     *
     * @return array
     */
    public function all()
    {
        $this->run();

        $res = array();
        while ($one = $this->one())
        {
            $res[] = $one;
        }

        return $res;
    }

    /**
     * Return first fetch Model instance.
     *
     * @return Model
     */
    protected function row()
    {
        $res = array();

        $reversedPK = $this->_table->isSimplePrimaryKey
            ? array('id' => 0)
            : array_flip((array) $this->_table->primaryKey);

        $resId      = null;

        // We want one resulting object. But we may have to process several lines in case that eager
        // load of related models have been made with has_many or many_many relations.
        while (
               ($row = $this->_pendingRow)                    !== false ||
               ($row = $this->_sth->fetch(\PDO::FETCH_ASSOC)) !== false
        ) {

            if ($this->_pendingRow)
            {
                // Prevent infinite loop.
                $this->_pendingRow = false;
                // Pending row is already parsed.
                $parsedRow = $row;
            }
            else
            {
                $parsedRow = $this->_parseRow($row);
            }

            // New main object, we are finished.
            if ($res && $resId !== array_intersect_key($parsedRow, $reversedPK)) // Compare IDs
            {
                $this->_pendingRow = $parsedRow;
                break;
            }

            // Same row but there is no linked model to fetch. Weird. Query must be not well
            // constructed. (Lack of GROUP BY).
            if ($res && !isset($parsedRow['_WITH_']))
            {
                continue;
            }

            // Now, we have to combined new parsed row with our constructing result.

            if ($res)
            {
                // Merge related models.
                $res = \SimpleAR\array_merge_recursive_distinct($res, $parsedRow);
            }
            else
            {
                $res   = $parsedRow;

                // Store result object ID for later use.
                $resId = array_intersect_key($res, $reversedPK);
            }
        }

        return $res;
    }

    /**
     * @return SimpleAR\Model
     */
    public function one()
    {
        $this->run();

        if ($row = $this->row())
        {
            $class = $this->_model;
            return $class::createFromRow($row, $this->_options);
        }

        return null;
    }

    protected function _buildAggregate($aggregate)
    {
        if ($aggregate['relations'])
        {
            $this->_useAlias = true;
        }
        if ($aggregate['asRelations'])
        {
            $this->_useResultAlias = true;
        }

        $node  = $this->_join($aggregate['relations'], Arbo::JOIN_LEFT);

        $model = $node->model;
        $table = $model::table();

        $column = (array) $table->columnRealName($aggregate['attribute']);
        $column = $column[0];

        $qInside = DB::quote($node->alias) . '.' . DB::quote($column);
        $alias = $aggregate['asRelations'] ? implode('.', $aggregate['asRelations']) . '.' : '';
        $qAlias  = DB::quote($alias . $aggregate['asAttribute']);

        $this->_aggregates[] = $aggregate['fn'] . '(' . $qInside . ') AS ' . $qAlias;
    }

    protected function _buildFilter(Option\Filter $o)
    {
        $attributes = $o->attributes;

        $columns = $o->toColumn
            ? $this->_table->columnRealName($attributes)
            : $attributes;

        $this->_filter[] = array(
            'node'    => $this->_arborescence,
            'columns' => $o->toColumn ? array_combine($attributes, $columns) : $columns
        );
    }

    protected function _buildGroup(Option\Group $o)
    {
        foreach ($o->groups as $group)
        {
            $this->_buildGroupOption($group);
        }
    }

    protected function _buildGroupOption($groupArray)
    {
        $node = $this->_join($groupArray['relations']);

        if ($groupArray['toColumn'])
        {
            $table   = $node->isRoot() ? $this->_table : $node->relation->cm->t;
            $columns = (array) $table->columnRealName($groupArray['attribute']);
        }
        else
        {
            $columns = (array) $groupArray['attribute'];
        }

        $this->_groups[] = array(
            'node'    => $node,
            'columns' => $columns,
        );
    }

    protected function _buildHaving($having)
    {
        if ($rels = $having['asRelations'])
        {
            $this->_useResultAlias = true;
        }

        $this->_havings[] = array(
            'relations' => $having['asRelations'],
            'attribute' => $having['asAttribute'],
            'operator'  => $having['operator'],
            'value'     => $having['value'],
        );
    }

    protected function _buildLimit(Option\Limit $o)
    {
        $this->_limit = $o->limit;
    }

    protected function _buildOffset(Option\Offset $o)
    {
        $this->_offset = $o->offset;
    }

    protected function _buildOrder(Option\Order $o)
    {
        foreach ($o->groups as $group)
        {
            $this->_buildGroupOption($group);
        }

        foreach ($o->aggregates as $aggregate)
        {
            $this->_buildAggregate($aggregate);
        }

        foreach ($o->orders as $order)
        {
            $this->_buildOrderOption($order);
        }
    }

    protected function _buildOrderOption($order)
    {
        $node = $this->_join($order['relations']);

        $qAlias  = DB::quote($node->alias);
        $qColumn = DB::quote($order['attribute']);

        $this->_orders[] = $qAlias . '.' . $qColumn . ' ' . $order['direction'];
    }

    protected function _buildWith(Option\With $o)
    {
        foreach ($o->groups as $group)
        {
            $this->_buildGroupOption($group);
        }

        foreach ($o->aggregates as $aggregate)
        {
            $this->_buildAggregate($aggregate);
        }

        foreach ($o->withs as $with)
        {
            $node  = $this->_join($with['relations'], Arbo::JOIN_LEFT);
            $model = $node->model;
            $table = $model::table();

            $this->_columns[] = array(
                'node' => $node,
                'columns' => $table->columns,
            );
        }
    }

    public function compile($components)
    {
        if (! $this->_filter)
        {
            $this->option('filter', null, true);
        }

        $this->_columns = array_merge($this->_filter, $this->_columns);

        return parent::compile($components);
    }

    protected function _compileColumns()
    {
        $d = $this->_distinct ? 'DISTINCT ' : '';

        $columns = array();
        foreach ($this->_columns as $group)
        {
            $node = $group['node'];

            $columns = array_merge($columns, $this->columnAliasing(
                $group['columns'],
                $this->_useAlias       ? $node->alias : '',
                $this->_useResultAlias ? $node->alias : ''
            ));
        }

        $this->_sql .= 'SELECT ' . $d . implode(',', $columns);
    }

    protected function _compileAggregates()
    {
        if ($this->_aggregates)
        {
            $this->_sql .= ',' . implode(',', $this->_aggregates);
        }
    }

    protected function _compileFrom()
    {
        $tName  = $this->_table->name;
        $tAlias = $this->_rootAlias;

        $this->_sql .= ' FROM ' . ($this->_useAlias
            ?' `' . $tName . '` `' .  $tAlias . '`'
            :' `' . $tName . '`'
            );

        if ($this->_arborescence
            && $join = $this->_arborescence->toSql())
        {
            $this->_sql .= $join;
        }
    }

    protected function _compileGroups()
    {
        $groups = array();
        foreach ($this->_groups as $group)
        {
            $qAlias = $this->_useAlias ? DB::quote($group['node']->alias) . '.' : '';

            foreach ($group['columns'] as $column)
            {
                $groups[] = $qAlias . DB::quote($column);
            }

        }

        $this->_sql .= ' GROUP BY ' . implode(',', $groups);
    }

    protected function _compileOrders()
    {
        $this->_sql .= ' ORDER BY ' . implode(',', $this->_orders);
    }

    protected function _compileHavings()
    {
        $havings = array();
        foreach ($this->_havings as $h)
        {
            $alias = $this->_useResultAlias
                ? implode('.', array_merge(array($this->_rootAlias), $h['relations'])) . '.'
                : '';

            $havings[] = DB::quote($alias . $h['attribute']) . ' ' .  $h['operator'] . ' ' . $h['value'];
        }

        $this->_sql .= ' HAVING ' . implode(',', $havings);
    }

    protected function _compileLimit()
    {
        $this->_sql .= ' LIMIT ' . $this->_limit;
    }

    protected function _compileOffset()
    {
        $this->_sql .= ' OFFSET ' . $this->_offset;
    }

    /**
     * Parse a row fetch from query result into a more object-oriented array.
     *
     * Resulting array:
     * array(
     *      'attr1' => <val>,
     *      'attr2' => <val>,
     *      'attr3' => <val>,
     *      '_WITH_' => array(
     *          'relation1' => array(
     *              <attributes>
     *          ),
     *          ...
     *      ),
     * )
     *
     * Note: does not parse relations recursively (only on one level).
     *
     * @return array
     */
    private function _parseRow($row)
    {
        $res = array();

        foreach ($row as $key => $value)
        {
            // We do not want null values. It would result with linked model instances with null 
            // attributes and null IDs. Moreover, it reduces process time (does not process useless 
            // null-valued attributes).
            //
            // EDIT
            // ----
            // Note: Now, the check is made in Model::_load(). We don't keep linked models with null 
            // ID at that moment.
            // Why: by discarding attributes with null value here, object's attribute array was not 
            // filled with them and we were forced to check attribute presence in columns definition 
            // in Model::__get(). Not nice.
            //
            // if ($value === null) { continue; }

            // Keys are prefixed with an alias corresponding:
            // - either to the root model;
            // - either to a linked model (thanks to the relation name).
            if ($this->_useResultAlias)
            {
                $a = explode('.', $key);

                if ($a[0] === $this->_rootAlias)
                {
                    // $a[1]: table column name.

                    $res[$a[1]] = $value;
                }
                else
                {
                    // $a[0]: relation name.
                    // $a[1]: linked table column name.

                    $res['_WITH_'][$a[0]][$a[1]] = $value;
                }
            }

            // Much more simple in that case.
            else
            {
                $res[$key] = $value;
            }
        }

        return $res;
    }

}
