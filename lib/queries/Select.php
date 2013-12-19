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
class Select extends \SimpleAR\Query\Where
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
    private $_aHaving = array();

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

            // Prevent infinite loop.
            if ($this->_aPendingRow) { $this->_aPendingRow = false; }

            $aParsedRow = $this->_parseRow($aRow);

            // New main object, we are finished.
            if ($aRes && $aResId !== array_intersect_key($aParsedRow, $aReversedPK)) // Compare IDs
            {
                $this->_aPendingRow = $aRow;
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

	private function _group_by($aGroupBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        foreach ($aGroupBy as $sAttribute)
        {
            $aPieces = explode('/', $sAttribute);

			// One or several attributes of a related model.
            $aAttributes = explode(',', array_pop($aPieces));

			// Add related model(s) in join arborescence.
            list(, $oRelation) = $this->_addToArborescence($aPieces, self::JOIN_INNER, true);

            $sResultAlias    = $oRelation ? $oRelation->name  : self::ROOT_RESULT_ALIAS;
            $oTableToGroupOn = $oRelation ? $oRelation->lm->t : $this->_oRootTable;

            foreach ((array) $aAttributes as $sAttribute)
            {
                if (! $oTableToGroupOn->hasAttribute($sAttribute))
                {
                    throw new Exception('Attribute "' . $sAttribute . '" does not exist for model "' . $oTableToGroupOn->modelBaseName . '" in group_by option.');
                }

                $iDepth = count($aPieces) ?: '';

                $this->_aGroupBy[] = '`' . $oTableToGroupOn->alias . $iDepth . '`.' .  $oTableToGroupOn->columnRealName($sAttribute);
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

            $aRelationPieces = explode('/', $sAttribute);
			$sAttribute      = array_pop($aRelationPieces);

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

			if ($sAttribute[0] !== '#')
			{
                // Add related model(s) in join arborescence.
                list($oNode, $oRelation) = $this->_addToArborescence($aRelationPieces);

                $oCMTable = $oRelation ? $oRelation->cm->t : $this->_oRootTable;
                $oLMTable = $oRelation ? $oRelation->lm->t : null;

                $iDepth        = count($aRelationPieces) ?: '';

                // We *have to* include relation if we have to order on one of 
                // its fields.
                if ($sAttribute !== 'id')
                {
                    $oNode->__force = true;
                }

                if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                {
                    $oNode->__force = true;
                    $aRes[] = $oLMTable->alias . $iDepth . '.' .  $oLMTable->columnRealName($sAttribute) . ' ' . $sOrder;
                }
                elseif ($oRelation instanceof \SimpleAR\ManyMany)
                {
                    if ($sAttribute === 'id')
                    {
                        $aRes[] = $oRelation->jm->alias . $iDepth . '.' .  $oRelation->jm->to . ' ' . $sOrder;
                    }
                    else
                    {
                        $aRes[] = $oLMTable->alias . $iDepth . '.' .  $oLMTable->columnRealName($sAttribute) . ' ' . $sOrder;
                    }
                }
                elseif ($oRelation instanceof \SimpleAR\BelongsTo)
                {
                    if ($sAttribute === 'id')
                    {
                        $aRes[] = $oCMTable->alias . ($iDepth - 1 ?: '') . '.' .  $oRelation->cm->column . ' ' . $sOrder;
                    }
                    else
                    {
                        $aRes[] = $oLMTable->alias . $iDepth . '.' .  $oLMTable->columnRealName($sAttribute) . ' ' . $sOrder;
                    }
                }
                else
                {
                    $aRes[] = $oCMTable->alias . '.' .  $oCMTable->columnRealName($sAttribute) . ' ' . $sOrder;
                }

                // Previously, ORDER BY was made on table alias and column name:
                //$aRes[] = $oCurrentTable->alias . $iDepth . '.' .  $oCurrentTable->columnRealName($sAttribute) . ' ' . $sOrder;

                // Now, it is made on result alias and attribute name:
                //$aRes[] = '`' . $sResultAlias . '.' . $sAttribute . '` ' . $sOrder;
			}
            // Treatment is different if have to order on a COUNT.
            else
            {
                // Without "#"
                $sAttribute = substr($sAttribute, 1);

                // If the *order by* is made on a relation count, re-push the relation name in the
                // relation array because we want to add it the arborescence.
                array_push($aRelationPieces, $sAttribute);
                $iDepth = count($aRelationPieces);

                // Add related model(s) in join arborescence.
                list($oNode, $oRelation) = $this->_addToArborescence($aRelationPieces, self::JOIN_LEFT, true);

                $oTableToGroupOn = $oRelation ? $oRelation->cm->t : $this->_oRootTable;

                if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                {
                    $sTableAlias = $oRelation->lm->alias;
                    $sKey		 = $oRelation->lm->column;
                }
                elseif ($oRelation instanceof \SimpleAR\ManyMany)
                {
                    $sTableAlias = $oRelation->jm->alias;
                    $sKey		 = $oRelation->jm->from;
                }
                else // BelongsTo
                {
                    $sTableAlias = $oRelation->cm->alias;
                    $sKey		 = $oRelation->cm->column;

                    // We do not *need* to join this relation since we have a corresponding
                    // attribute in current model. Moreover, this is stupid to COUNT on a BelongsTo
                    // relationship since it would return 0 or 1.
                    $oNode->__force = false;
                }

                $sTableAlias .= ($iDepth === 0) ? '' : $iDepth;

                // Count alias: `<result alias>.#<relation name>`;
                $this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS `' .  $sResultAlias . '.#' . $sAttribute . '`';
                
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
            }
        }

        $this->_sOrderBy = $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
	}

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build($aOptions)
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

        $this->_processArborescence();
        $this->_where();

		$this->sql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
		$this->sql .= $this->_groupBy();
		$this->sql .= $this->_sOrderBy;
        $this->sql .= $this->_aHaving ? (' HAVING ' . implode(',', $this->_aHaving)) : '';

		if (isset($aOptions['limit']))
		{
			$this->sql .= ' LIMIT ' . $aOptions['limit'];
		}

		if (isset($aOptions['offset']))
		{
			$this->sql .= ' OFFSET ' . $aOptions['offset'];
		}
	}


	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

    protected function _having($sAttribute, $sOperator, $mValue)
    {
        $aPieces    = explode('/', $sAttribute);
        $sAttribute = array_pop($aPieces);

        $sPrefix    = $sAttribute[0];
        $sAttribute = substr($sAttribute, 1);
        $aPieces[]  = $sAttribute;
        $iDepth     = count($aPieces) ?: '';

        list(, $oRelation, $oPreviousRelation) = $this->_addToArborescence($aPieces, self::JOIN_LEFT, true);
        $sResultAlias = $oPreviousRelation ? $oPreviousRelation->name : self::ROOT_RESULT_ALIAS;
        $oTable     = $oPreviousRelation ? $oPreviousRelation : $this->_oRootTable;
        $sColumnToCountOn = $oRelation->lm->t->isSimplePrimaryKey ? $oRelation->lm->t->primaryKey : $oRelation->lm->t->primaryKeyColumns[0];

        switch ($sPrefix)
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

    private function _parseRow($aRow)
    {
        $aRes = array();

        foreach ($aRow as $sKey => $mValue)
        {
            $a = explode('.', $sKey);

            if ($a[0] === self::ROOT_RESULT_ALIAS)
            {
                // $a[1]: table column name.

                $aRes[$a[1]] = $mValue;
            }
            else
            {
                // $a[0]: relation name.
                // $a[1]: related table column name.

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
            $aRelationPieces = explode('/', $sRelation);
            $iDepth          = count($aRelationPieces); // I don't like this.

            list(, $oRelation) = $this->_addToArborescence($aRelationPieces, self::JOIN_LEFT, true);

            $sLM = $oRelation->lm->class;
            $this->_aSelects = array_merge(
                $this->_aSelects,
                $sLM::columnsToSelect($oRelation->filter, $oRelation->lm->alias . ($iDepth === 0 ?  '' : $iDepth), $sRelation)
            );
        }
    }

}
