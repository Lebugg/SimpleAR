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
	private $_selects		= array();

    /**
     * Contains attributes to GROUP BY on.
     *
     * @var array
     */
	private $_groupBys = array();
	private $_orde_bys = array();
    private $_havings  = array();
    private $_limit;
    private $_offset;

    private $_pendingRow = false;

    protected static $_options = array('conditions', 'filter', 'group_by',
            'has', 'limit', 'offset', 'order_by', 'with');

    const DEFAULT_ROOT_RESULT_ALIAS = '_';

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
        $res = array();

        while ($one = $this->row())
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
    public function row()
    {
        $res = array();

        $reverse_pK = $this->_context->rootTable->isSimplePrimaryKey ?  array('id' => 0) : array_flip((array) $this->_context->rootTable->primar_key);
        $re_id      = null;

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
                $parse_row = $row;
            }
            else
            {
                $parse_row = $this->_parseRow($row);
            }

            // New main object, we are finished.
            if ($res && $re_id !== array_intersect_key($parse_row, $reverse_pK)) // Compare IDs
            {
                $this->_pendingRow = $parse_row;
                break;
            }

            // Same row but there is no linked model to fetch. Weird. Query must be not well
            // constructed. (Lack of GROUP BY).
            if ($res && !isset($parse_row['_WITH_']))
            {
                continue;
            }

            // Now, we have to combined new parsed row with our constructing result.

            if ($res)
            {
                // Merge related models.
                $res = \array_merge_recursive_distinct($res, $parse_row);
            }
            else
            {
                $res   = $parse_row;

                // Store result object ID for later use.
                $re_id = array_intersect_key($res, $reverse_pK);
            }
        }

        return $res;
    }

    protected function _build(array $options)
    {
        // If we don't set a filter entry, Select::filter() will never be
        // called.
        if (! isset($options['filter']))
        {
            $options['filter'] = null;
        }

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
		$this->_sql  = 'SELECT ' . implode(', ', $this->_selects);

        $this->_sql .= $this->_context->useAlias
            ?' FROM `' . $this->_context->rootTableName . '` `' .  $this->_context->rootTableAlias . '`'
            :' FROM `' . $this->_context->rootTableName . '`'
            ;

        $this->_sql .= ' ' . $this->_join();
		$this->_sql .= $this->_where();
		$this->_sql .= $this->_groupBys ? ' GROUP BY ' . implode(',',
        $this->_groupBys) : '';
        $this->_sql .= $this->_orde_bys ? ' ORDER BY ' . implode(',', $this->_orde_bys) : '';
        $this->_sql .= $this->_havings  ? ' HAVING '   . implode(',',
        $this->_havings)  : '';
        $this->_sql .= $this->_limit   ? ' LIMIT '    . $this->_limit                 : '';
        $this->_sql .= $this->_offset  ? ' OFFSET '   . $this->_offset                : '';
    }

    protected function _conditions(Option $option)
    {
        // @see Option\Conditions::build() to check returned array format.
        $res = $option->build();

        if ($this->_conditions)
        {
            $this->_conditions->combine($res['conditions']);
        }
        else
        {
            $this->_conditions = $res['conditions'];
        }

        $this->_havings    = array_merge($this->_havings,    $res['havings']);
        $this->_groupBys    = array_merge($this->_groupBys,  $res['groupBys']);
        $this->_selects    = array_merge($this->_selects,    $res['selects']);
    }

    protected function _group_by(Option $option)
    {
        $this->_groupBys = array_merge($this->_groupBys, $option->build());
    }

    protected function _filter(Option $option)
    {
		$this->_selects = array_merge($this->_selects, $option->build());
    }

    protected function _limit(Option $option)
    {
        $this->_limit = $option->build();
    }

    protected function _offset(Option $option)
    {
        $this->_offset = $option->build();
    }

	protected function _order_by(Option $option)
	{
        $res = $option->build();

        $this->_orde_bys = array_merge($this->_orde_bys, $res['order_by']);
        $this->_groupBys = array_merge($this->_groupBys, $res['group_by']);
        $this->_selects = array_merge($this->_selects, $res['selects']);
	}

    protected function _with(Option $option)
    {
        $this->_selects = array_merge($this->_selects, $option->build());
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

}
