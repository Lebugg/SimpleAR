<?php
/**
 * This file contains the Select class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles SELECT statements.
 */
class Select extends Where
{
    /**
     * Contains all attributes to fetch.
     *
     * @var array
     */
    protected $_distinct = false;
    protected $_filter;
    protected $_from;
	protected $_orders = array();
    protected $_limit;
    protected $_offset;

    private $_pendingRow = false;

    protected static $_options = array('conditions', 'filter', 'group', 'group_by',
            'has', 'limit', 'offset', 'order', 'order_by', 'with');

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
        'from',
        'where',
        'groups',
        'orders',
        'havings',
        'limit',
        'offset',
    );

    const DEFAULT_ROOT_RESULT_ALIAS = '_';

    public function __construct($root)
    {
        parent::__construct($root);
        
        $this->_from = $this->_context->rootTableName;
    }

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

        $reversedPK = $this->_context->rootTable->isSimplePrimaryKey
            ? array('id' => 0)
            : array_flip((array) $this->_context->rootTable->primaryKey);

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
                $res = \array_merge_recursive_distinct($res, $parsedRow);
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
            $class = $this->_context->rootModel;
            return $class::createFromRow($row, $this->_givenOptions);
        }

        return null;
    }

    protected function _build(array $options)
    {
        // If user does not define any filter entry, we set a default one.
        /*
        if (! isset($options['filter']))
        {
            $options['filter'] = null;
            //$this->_filter = Option::forge('filter', null, $this->_context);
        }
        */

        // We have to use result alias in order to distinguish root model from
        // its linked models. It will cost more operations to parse result.
        //
        // @see Select::_parseRow().
        if (! empty($options['with']))
        {
            $this->_context->useResultAlias = true;
        }

        return parent::_build($options);
    }

    protected function _compile()
    {
        // Here we go? Let's check that we are selecting some columns.
        if (! $this->_filter)
        {
            $option = Option::forge('filter', null, $this->_context);
            $this->_handleOption($option);
        }

        $this->_columns = array_merge($this->_filter, $this->_columns);

        return parent::_compile();
    }

    protected function _compileColumns()
    {
        $d = $this->_distinct ? 'DISTINCT ' : '';

        $this->_sql .= 'SELECT ' . $d . implode(',', $this->_columns);
    }

    protected function _compileFrom()
    {
        $c = $this->_context;
        $this->_sql .= ' FROM ' . ($c->useAlias
            ?' `' . $c->rootTableName . '` `' .  $c->rootTableAlias . '`'
            :' `' . $c->rootTableName . '`'
            );

        $this->_sql .= ' ' . $this->_join();
    }

    protected function _compileGroups()
    {
        $this->_sql .= ' GROUP BY ' . implode(',', $this->_groups);
    }

    protected function _compileOrders()
    {
        $this->_sql .= ' ORDER BY ' . implode(',', $this->_orders);
    }

    protected function _compileHavings()
    {
        $this->_sql .= ' HAVING ' . implode(',', $this->_havings);
    }

    protected function _compileLimit()
    {
        $this->_sql .= ' LIMIT ' . $this->_limit;
    }

    protected function _compileOffset()
    {
        $this->_sql .= ' OFFSET ' . $this->_offset;
    }

    protected function _handleOption(Option $option)
    {
        switch (get_class($option))
        {
            case 'SimpleAR\Query\Option\Filter':
                $this->_filter = $option->columns;
                break;
            case 'SimpleAR\Query\Option\Group':
                $this->_groups = $option->groups;
                break;
            case 'SimpleAR\Query\Option\Limit':
                $this->_limit = $option->limit;
                break;
            case 'SimpleAR\Query\Option\Offset':
                $this->_offset = $option->offset;
                break;
            case 'SimpleAR\Query\Option\Order':
                $this->_orders  = $option->orders;
                $this->_groups  = array_merge($this->_groups, $option->groups);
                $this->_columns = array_merge($this->_columns, $option->columns);
                break;
            case 'SimpleAR\Query\Option\With':
                $this->_columns = array_merge($this->_columns, $option->columns);
                if ($option->groups)
                {
                    $this->_groups  = array_merge($this->_groups, $option->groups);
                }
                break;
            default:
                parent::_handleOption($option);
        }
    }

    protected function _initContext($root)
    {
        parent::_initContext($root);

        // False by default. It will be set true if we have to fetch
        // attributes from other tables. This is checked in
        // Select::_build().
        $this->_context->useResultAlias = false;
        
        // Contain the result alias we will use if we use result aliases.
        $this->_context->rootResultAlias = self::DEFAULT_ROOT_RESULT_ALIAS;
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
            if ($this->_context->useResultAlias)
            {
                $a = explode('.', $key);

                if ($a[0] === $this->_context->rootResultAlias)
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

    public function sum($column)
    {
        $this->_filter = array('SUM(' . $column . ')');
        $this->run();
        return $this->_sth->fetch(\PDO::FETCH_COLUMN);
    }
}
