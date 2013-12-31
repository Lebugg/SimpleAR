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
     * Contains ORDER BY clause.
     *
     * @var string
     */
	private $_sOrderBy;

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

    const ROOT_RESULT_ALIAS = '_';

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

        $aReversedPK = $this->_oRootTable->isSimplePrimaryKey ? array('id' => 0) : array_flip((array) $this->_oRootTable->primaryKey);
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

	private function _group_by(array $aGroupBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        foreach ($aGroupBy as $sAttribute)
        {
            $oAttribute = $this->_attribute($sAttribute);

			// Add related model(s) in join arborescence.
            $oNode           = $this->_addToArborescence($oAttribute->pieces, self::JOIN_INNER, true);

            $oTableToGroupOn = $oNode->table;
            $sTableAlias     = $oTableToGroupOn->alias . ($oNode->depth ?: '');

            foreach ((array) $oTableToGroupOn->columnRealName($oAttribute->attribute) as $sColumn)
            {
                $this->_aGroupBy[] = '`' . $sTableAlias . '`.' .  $sColumn;
            }
        }
	}

    /**
     * Only works when $_bUseModel and $_bUseAliases are true.
     */
	private function _order_by(array $aOrderBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge($this->_oRootTable->orderBy, $aOrderBy) as $sAttribute => $sOrder)
        {
            // Allows for a without-ASC/DESC syntax.
            if (is_int($sAttribute))
            {
                $sAttribute = $sOrder;
                $sOrder     = 'ASC';
            }

            $oAttribute = $this->_attribute($sAttribute);

            /*
            // Result alias will be the last-but-one relation name.
            if ($aRelationPieces)
            {
                $sResultAlias = end($aRelationPieces); reset($aRelationPieces);
            }
            // Or the root model symbol.
            else
            {
                $sResultAlias = self::ROOT_RESULT_ALIAS;
            }
            */

            if ($oAttribute->specialChar)
            {
                switch ($oAttribute->specialChar)
                {
                    case '#':
                        // Add related model(s) in join arborescence.
                        $oAttribute->pieces[] = $oAttribute->attribute;
                        $oNode     = $this->_addToArborescence($oAttribute->pieces, self::JOIN_LEFT, true);
                        $oRelation = $oNode->relation;

                        $oTableToGroupOn = $oRelation ? $oRelation->cm->t : $this->_oRootTable;
                        $sResultAlias    = $oAttribute->lastRelation ?: self::ROOT_RESULT_ALIAS;

                        // We assure that keys are string because they might be arrays.
                        if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                        {
                            $sTableAlias = $oRelation->lm->alias;
                            $sKey		 = is_string($oRelation->lm->column) ?  $oRelation->lm->column : $oRelation->lm->column[0];
                        }
                        elseif ($oRelation instanceof \SimpleAR\ManyMany)
                        {
                            $sTableAlias = $oRelation->jm->alias;
                            $sKey		 = is_string($oRelation->jm->from) ? $oRelation->jm->from : $oRelation->jm->from[0];
                        }
                        else // BelongsTo
                        {
                            $sTableAlias = $oRelation->cm->alias;
                            $sKey		 = is_string($oRelation->cm->column) ?  $oRelation->cm->column : $oRelation->cm->column[0];

                            // We do not *need* to join this relation since we have a corresponding
                            // attribute in current model. Moreover, this is stupid to COUNT on a BelongsTo
                            // relationship since it would return 0 or 1.
                            $oNode->__force = false;
                        }

                        $sTableAlias .= ($oNode->depth ?: '');

                        // Count alias: `<result alias>.#<relation name>`;
                        $this->_aSelects[] = 'COUNT(`' . $sTableAlias . '`.' .  $sKey . ') AS `' .  $sResultAlias . '.#' . $oAttribute->attribute . '`';

                        // We need a GROUP BY.
                        if ($oTableToGroupOn->isSimplePrimaryKey)
                        {
                            $this->_aGroupBy[] = '`' . $sResultAlias . '.id`';
                        }
                        else
                        {
                            foreach ($oTableToGroupOn->primaryKey as $sPK)
                            {
                                $this->_aGroupBy[] = '`' . $sResultAlias . '.' . $sPK . '`';
                            }
                        }

                        $this->_aOrderBy[] = '`' . $sResultAlias . '.#' . $oAttribute->attribute .'`';
                        break;
                }
            }
            else
            {
                // Add related model(s) in join arborescence.
                $oNode     = $this->_addToArborescence($oAttribute->pieces);
                $oRelation = $oNode->relation;

                $oCMTable = $oRelation ? $oRelation->cm->t : $this->_oRootTable;
                $oLMTable = $oRelation ? $oRelation->lm->t : null;

                $iDepth = $oNode->depth ?: '';

                // ORDER BY is made on related model's attribute.
                if ($oNode->relation)
                {
                    // We *have to* include relation if we have to order on one of 
                    // its fields.
                    if ($oAttribute->attribute !== 'id')
                    {
                        $oNode->__force = true;
                    }

                    if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                    {
                        $oNode->__force = true;

                        $sAlias  = $oLMTable->alias . $iDepth;
                        $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                    }
                    elseif ($oRelation instanceof \SimpleAR\ManyMany)
                    {
                        if ($sAttribute === 'id')
                        {
                            $sAlias  = $oRelation->jm->alias . $iDepth;
                            $mColumn = $oRelation->jm->to;
                        }
                        else
                        {
                            $sAlias  = $oLMTable->alias . $iDepth;
                            $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                        }
                    }
                    elseif ($oRelation instanceof \SimpleAR\BelongsTo)
                    {
                        if ($sAttribute === 'id')
                        {
                            $sAlias  = $oCMTable->alias . ($iDepth - 1 ?: '');
                            $mColumn = $oRelation->cm->column;
                        }
                        else
                        {
                            $sAlias  = $oLMTable->alias . $iDepth;
                            $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                        }
                    }
                }
                // ORDER BY is made on a current model's attribute.
                else
                {
                    $sAlias  = $oCMTable->alias;
                    $mColumn = $oCMTable->columnRealName($oAttribute->attribute);
                }

                foreach ((array) $mColumn as $sColumn)
                {
                    $this->_aOrderBy[] = $sAlias . '.' .  $sColumn . ' ' . $sOrder;
                }
			}
        }

        //$this->_sOrderBy = $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
	}

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	protected function _build(array $aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		$this->_aSelects = (isset($aOptions['filter']))
			? $sRootModel::columnsToSelect($aOptions['filter'], $sRootAlias, self::ROOT_RESULT_ALIAS)
			: $sRootModel::columnsToSelect(null,                $sRootAlias, self::ROOT_RESULT_ALIAS)
			;

		if (isset($aOptions['conditions']))
		{
            $this->_conditions($aOptions['conditions']);
		}

        if (isset($aOptions['has']))
        {
            $this->_has((array) $aOptions['has']);
        }

		if (isset($aOptions['order_by']))
		{
			$this->_order_by((array) $aOptions['order_by']);
		}

		if (isset($aOptions['group_by']))
		{
			$this->_group_by((array) $aOptions['group_by']);
		}

        if (isset($aOptions['with']))
        {
			$this->_with($aOptions['with']);
        }

		if (isset($aOptions['limit']))
		{
            $this->_limit($aOptions['limit']);
		}

		if (isset($aOptions['offset']))
		{
            $this->_offset($aOptions['offset']);
		}
	}

    protected function _compile()
    {
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

        $this->_processArborescence();
        $this->_where();

		$this->sql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->sql .= ' FROM `' . $this->_oRootTable->name . '` ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
		$this->sql .= $this->_groupBy();
		$this->sql .= $this->_sOrderBy;
        $this->sql .= $this->_aHaving ? (' HAVING ' . implode(',', $this->_aHaving)) : '';
        $this->sql .= $this->_iLimit ? ' LIMIT ' . $this->_iLimit : '';
        $this->sql .= $this->_iOffset ? ' OFFSET ' . $this->_iOffset : '';
    }


	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

    protected function _having($oAttribute, $sOperator, $mValue)
    {
        $oAttribute->pieces[] = $oAttribute->attribute;
        $oNode = $this->_addToArborescence($oAttribute->pieces, self::JOIN_LEFT, true);

        $sOperator = $sOperator ?: Condition::DEFAULT_OP;
        $sResultAlias     = $oAttribute->lastRelation ?: self::ROOT_RESULT_ALIAS;
        $sAttribute       = $oAttribute->attribute;
        $oRelation = $oNode->relation;
        $oTable           = $oNode->previousRelation ? $oNode->previousRelation : $this->_oRootTable;
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
                    $this->values[]    = $mValue;
                    break;
            }
        }
    }

    private function _limit($i)
    {
        $i = (int) $i;

        if ($i < 0)
        {
            throw new Exception('"limit" option must be a natural integer. Negative integer given: ' . $i . '.');
        }

        $this->_iLimit = $i;
    }

    private function _offset($i)
    {
        $i = (int) $i;

        if ($i < 0)
        {
            throw new Exception('"offset" option must be a natural integer. Negative integer given: ' . $i . '.');
        }

        $this->_iOffset = $i;
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
    private function _parseRow($aRow)
    {
        $aRes = array();

        foreach ($aRow as $sKey => $mValue)
        {
            // We do not want null values. It would result with linked model instances with null 
            // attributes and null IDs. Moreover, it reduces process time (does not process useless 
            // null-valued attributes).
            if ($mValue === null) { continue; }

            $a = explode('.', $sKey);

            if ($a[0] === self::ROOT_RESULT_ALIAS)
            {
                // $a[1]: table column name.

                $aRes[$a[1]] = $mValue;
            }
            else
            {
                // $a[0]: relation name.
                // $a[1]: linked table column name.

                $aRes['_WITH_'][$a[0]][$a[1]] = $mValue;
            }
        }

        return $aRes;
    }

    private function _with($m)
    {
        $a = (array) $m;

        foreach($a as $sRelation)
        {
            $oNode = $this->_addToArborescence(explode('/', $sRelation), self::JOIN_LEFT, true);
            $oRelation = $oNode->relation;

            $sLM = $oRelation->lm->class;
            $this->_aSelects = array_merge(
                $this->_aSelects,
                $sLM::columnsToSelect($oRelation->filter, $oRelation->lm->alias . ($oNode->depth ?: ''), $sRelation)
            );
        }
    }

}
