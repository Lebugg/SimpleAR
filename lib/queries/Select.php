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
	private $_aSelects		= array();

    /**
     * Contains attributes to GROUP BY on.
     *
     * @var array
     */
	private $_aGroupBy		= array();
	private $_aOrderBy		= array();
    private $_aHaving = array();
    private $_iLimit;
    private $_iOffset;

    private $_aPendingRow = false;

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
        $aRes = array();

        while ($aOne = $this->row())
        {
            $aRes[] = $aOne;
        }

        return $aRes;
    }

    /**
     * Return first fetch Model instance.
     *
     * @return Model
     */
    public function row()
    {
        $aRes = array();

        $aReversedPK = $this->_context->rootTable->isSimplePrimaryKey ?  array('id' => 0) : array_flip((array) $this->_context->rootTable->primaryKey);
        $aResId      = null;

        // We want one resulting object. But we may have to process several lines in case that eager
        // load of related models have been made with has_many or many_many relations.
        while (
               ($aRow = $this->_aPendingRow)                    !== false ||
               ($aRow = $this->_oSth->fetch(\PDO::FETCH_ASSOC)) !== false
        ) {

            if ($this->_aPendingRow)
            {
                // Prevent infinite loop.
                $this->_aPendingRow = false;
                // Pending row is already parsed.
                $aParsedRow = $aRow;
            }
            else
            {
                $aParsedRow = $this->_parseRow($aRow);
            }

            // New main object, we are finished.
            if ($aRes && $aResId !== array_intersect_key($aParsedRow, $aReversedPK)) // Compare IDs
            {
                $this->_aPendingRow = $aParsedRow;
                break;
            }

            // Same row but there is no linked model to fetch. Weird. Query must be not well
            // constructed. (Lack of GROUP BY).
            if ($aRes && !isset($aParsedRow['_WITH_']))
            {
                continue;
            }

            // Now, we have to combined new parsed row with our constructing result.

            if ($aRes)
            {
                // Merge related models.
                $aRes = array_merge_recursive_distinct($aRes, $aParsedRow);
            }
            else
            {
                $aRes   = $aParsedRow;

                // Store result object ID for later use.
                $aResId = array_intersect_key($aRes, $aReversedPK);
            }
        }

        return $aRes;
    }

	public function group_by($a)
	{
        $this->_aGroupBy = array_merge($this->_aGroupBy, $a);
	}

    /**
     * Handle "filter" option.
     *
     * First, it retrieve an attribute array from the Model.
     * Then, it apply aliasing according to contextual values.
     *
     * Final columns to select (in a string form) are stored in
     * Select::$_aSelects.
     *
     * @param string $sFilter The filter to apply or null to not filter.
     *
     * @return void
     *
     * @see Model::columnsToSelect()
     * @see Query::attributeAliasing()
     * @see Query\Select::$_aSelects
     */
    public function filter($a)
    {
		$this->_aSelects = array_merge($this->_aSelects, $a);
    }


    public function limit($i)
    {
        $this->_iLimit = $i;
    }

    public function offset($i)
    {
        $this->_iOffset = $i;
    }

	public function order_by(array $orderBy, array $groupBy = array(), array $selects = array())
	{
        $this->_aOrderBy = array_merge($this->_aOrderBy, $orderBy);
        $this->_aGroupBy = array_merge($this->_aGroupBy, $groupBy);
        $this->_aSelects = array_merge($this->_aSelects, $selects);
	}

    public function with($a)
    {
        $this->_aSelects = array_merge($this->_aSelects, $a);
    }

    protected function _build(array $aOptions)
    {
        // If we don't set a filter entry, Select::filter() will never be
        // called.
        if (! isset($aOptions['filter']))
        {
            $aOptions['filter'] = null;
        }

        // We have to use result alias in order to distinguish root model from
        // its linked models. It will cost more operations to parse result.
        //
        // @see Select::_parseRow().
        if (! empty($aOptions['with']))
        {
            $this->_context->useResultAlias = true;
        }

        parent::_build($aOptions);
    }

    protected function _compile()
    {
		$sRootModel   = $this->_context->rootModel;
		$sRootAlias   = $this->_context->rootTable->alias;

        $sJoin = $this->_processArborescence();

		$this->_sSql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->_sSql .= ' FROM `' . $this->_context->rootTableName . '` ' .  $sRootAlias .  ' ' . $sJoin;
		$this->_sSql .= $this->_where();
		$this->_sSql .= $this->_groupBy();
        $this->_sSql .= $this->_aOrderBy ? (' ORDER BY ' . implode(',', $this->_aOrderBy)) : '';
        $this->_sSql .= $this->_aHaving  ? (' HAVING ' . implode(',', $this->_aHaving))    : '';
        $this->_sSql .= $this->_iLimit   ? ' LIMIT ' . $this->_iLimit                      : '';
        $this->_sSql .= $this->_iOffset  ? ' OFFSET ' . $this->_iOffset                    : '';
    }

	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

    protected function _having($oAttribute, $sOperator, $mValue)
    {
        $oAttribute->pieces[] = $oAttribute->attribute;
        $oNode = $this->_context->arborescence->add($oAttribute->pieces,
        Arborescence::JOIN_LEFT, true);

        $sOperator = $sOperator ?: Condition::DEFAULT_OP;
        $sResultAlias     = $oAttribute->lastRelation ?: $this->_context->rootResultAlias;
        $sAttribute       = $oAttribute->attribute;
        $oRelation        = $oNode->relation;
        $oTable           = $oNode->previousRelation ? $oNode->previousRelation : $this->_context->rootTable;
        $sColumnToCountOn = $oRelation->lm->t->isSimplePrimaryKey ? $oRelation->lm->t->primaryKey : $oRelation->lm->t->primaryKeyColumns[0];
        $iDepth           = $oNode->depth ?: '';

        if ($oAttribute->specialChar)
        {
            switch ($oAttribute->specialChar)
            {
                case '#':
                    $this->_aSelects[] = 'COUNT(`' . $oRelation->lm->t->alias . $iDepth . '`.' .  $sColumnToCountOn . ') AS `' .  $sResultAlias . '.#' . $sAttribute . '`';
                    // We need a GROUP BY.
                    if ($oTable->isSimplePrimaryKey)
                    {
                        $this->_aGroupBy[] = '`' . $sResultAlias . '.id`';
                    }
                    else
                    {
                        foreach ($oTable->primaryKey as $sPK)
                        {
                            $this->_aGroupBy[] = '`' . $sResultAlias . '.' . $sPK . '`';
                        }
                    }
                    $this->_aHaving[]  = '`'. $sResultAlias . '.#' . $sAttribute . '` ' . $sOperator . ' ?';
                    $this->_aValues[]    = $mValue;
                    break;
            }
        }
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        if ($this->_context->useModel)
        {
            // False by default. It will be set true if we have to fetch
            // attributes from other tables. This is checked in
            // Select::_build().
            $this->_context->useResultAlias = false;
            
            // Contain the result alias we will use if we use result aliases.
            $this->_context->rootResultAlias = self::DEFAULT_ROOT_RESULT_ALIAS;
        }
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
