<?php
namespace SimpleAR\Query;

class Select extends \SimpleAR\Query\Where
{
	private $_aSelects		= array();
	private $_sOrderBy;
	private $_aGroupBy		= array();

    public function all()
    {
        $aRes = array();

        while ($aOne = $this->row())
        {
            $aRes[] = $aOne;
        }

        return $aRes;
    }

	public function build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		$this->_aSelects = (isset($aOptions['filter']))
			? $sRootModel::columnsToSelect($aOptions['filter'], $sRootAlias, '_')
			: $sRootModel::columnsToSelect(null, $sRootAlias, '_')
			;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

		if (isset($aOptions['order_by']))
		{
			$this->_analyzeOrderBy($aOptions['order_by']);
		}

        if (isset($aOptions['with']))
        {
			$this->_with($aOptions['with']);
        }

        $this->_arborescenceToSql();

		$this->sql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
		$this->sql .= $this->_groupBy();
		$this->sql .= $this->_sOrderBy;

		if (isset($aOptions['limit']))
		{
			$this->sql .= ' LIMIT ' . $aOptions['limit'];
		}

		if (isset($aOptions['offset']))
		{
			$this->sql .= ' OFFSET ' . $aOptions['offset'];
		}
	}

    public function row()
    {
        $aRes = array();

        $aReversedPK = $this->_oRootTable->isSimplePrimaryKey ? array('id' => 0) : array_flip((array) $this->_oRootTable->primaryKey);
        $aResId      = null;

        // We want one resulting object. But we may have to process several lines in case that eager
        // load of related models have been made with has_many or many_many relations.
        while (($aRow = $this->_oSth->fetch(\PDO::FETCH_ASSOC)) !== false)
        {
            $aParsedRow = $this->_parseRow($aRow);

            // New main object, we are finished.
            if ($aRes && $aResId !== array_intersect_key($aParsedRow, $aReversedPK)) // Compare IDs
            {
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

	private function _analyzeOrderBy($aOrderBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge($this->_oRootTable->orderBy, $aOrderBy) as $sAttribute => $sOrder)
        {
            $aPieces = explode('/', $sAttribute);
            $iCount  = count($aPieces);

			// Attribute of root model.
			if ($iCount === 1)
			{
				if ($sAttribute[0] === '#')
				{
					$aRes[] = $this->_orderByCount($sAttribute, $sOrder, $this->_sRootModel, '_', $this->_aArborescence);
					continue;
				}

				$aRes[] = $sRootAlias . '.' .  $this->_oRootTable->columnRealName($sAttribute) . ' ' . $sOrder;
				continue;
			}

			// Attribute of a related model.
			$sAttribute = array_pop($aPieces);

			// Add related model(s) in join arborescence.
            list($aArborescence, $oRelation) = $this->_addToArborescence($aPieces);
            $sCurrentModel = $oRelation ? $oRelation->lm->class : $this->_sRootModel;

			if ($sAttribute[0] === '#')
			{
				$aRes[] = $this->_orderByCount($sAttribute, $sOrder, $sCurrentModel, $oRelation->name, $aArborescence);
				continue;
			}

			$oCurrentTable = $sCurrentModel::table();
			$aRes[] = $oCurrentTable->alias . '.' .  $oCurrentTable->columnRealName($sAttribute) . ' ' . $sOrder;
        }

        $this->_sOrderBy = $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
	}

	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

	private function _orderByCount($sRelation, $sOrder, $sClass, $sResultAlias, &$aArborescence)
	{
        // $sCountAlias: `#<Relation name>`;
		$sCountAlias = '`' . $sResultAlias . '.' . $sRelation . '`';
		$sRelation   = substr($sRelation, 1);

		$oRelation   = $sClass::relation($sRelation);
		$oTable		 = $sClass::table();

		if (! isset($aArborescence[$sRelation]))
		{
			$aArborescence[$sRelation] = array();
		}
		// Go forward in arborescence.
		$aArborescence =& $aArborescence[$sRelation];
        $aArborescence['_TYPE_'] = self::JOIN_INNER;

		if ($oRelation instanceof \SimpleAR\HasMany)
		{
			$sTableAlias = $oRelation->lm->alias;
			$sKey		 = $oRelation->lm->column;

			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null, 'and');
		}
		elseif ($oRelation instanceof \SimpleAR\ManyMany)
		{
			$sTableAlias = $oRelation->jm->alias;
			$sKey		 = $oRelation->jm->from;

			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null, 'or');
		}
		elseif ($oRelation instanceof \SimpleAR\HasOne)
		{
			$sTableAlias = $oRelation->lm->alias;
			$sKey		 = $oRelation->lm->column;

			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null);
		}
		elseif ($oRelation instanceof \SimpleAR\BelongsTo)
		{
			$sTableAlias = $oRelation->cm->alias;
			$sKey		 = $oRelation->cm->column;
		}

		$this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS ' . $sCountAlias;
		$this->_aGroupBy[] = $oTable->alias . '.' . $oTable->primaryKey;

		return $sCountAlias . ' ' . $sOrder;
	}

    private function _parseRow($aRow)
    {
        $aRes = array();

        foreach ($aRow as $sKey => $mValue)
        {
            $a = explode('.', $sKey);

            if ($a[0] === '_')
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
            list($a, $oRelation) = $this->_addToArborescence(explode('/', $sRelation), self::JOIN_LEFT, true);

            $sLM = $oRelation->lm->class;
            $this->_aSelects = array_merge(
                $this->_aSelects,
                $sLM::columnsToSelect($oRelation->filter, $oRelation->lm->alias, $sRelation)
            );
        }
    }

}
