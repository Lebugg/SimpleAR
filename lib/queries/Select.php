<?php
namespace SimpleAR\Query;

class Select extends \SimpleAR\Query\Where
{
	private $_aSelects		= array();
	private $_sOrderBy;
	private $_aGroupBy		= array();

	public function build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		$this->_aSelects = (isset($aOptions['filter']))
			? $sRootModel::columnsToSelect($aOptions['filter'], $sRootAlias)
			: $sRootModel::columnsToSelect(null, $sRootAlias)
			;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

		if (isset($aOptions['order_by']))
		{
			$this->_analyzeOrderBy($aOptions['order_by']);
		}

		if (isset($aOptions['counts']))
		{
			$this->_analyzeCounts($aOptions['counts']);
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

	public function buildCount($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

        $this->_arborescenceToSql();

		$this->sql  = 'SELECT COUNT(*)';
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
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
					$aRes[] = $this->_orderByCount($sAttribute, $sOrder, $this->_sRootModel, $this->_aArborescence);
					continue;
				}

				$aRes[] = $sRootAlias . '.' .  $this->_oRootTable->columnRealName($sAttribute) . ' ' . $sOrder;
				continue;
			}

			// Attribute of a related model.
			$sAttribute = array_pop($aPieces);

			// Add related model(s) in join arborescence.
			$aArborescence =& $this->_aArborescence;
			$sCurrentModel =  $this->_sRootModel;
			foreach ($aPieces as $sRelation)
			{
				$oRelation = $sCurrentModel::relation($sRelation);
				if (! isset($aArborescence[$sRelation]))
				{
					$aArborescence[$sRelation] = array();
				}

				// Go forward in arborescence.
				$aArborescence =& $aArborescence[$sRelation];
				$sCurrentModel =  $oRelation->lm->class;
			}

			if ($sAttribute[0] === '#')
			{
				$aRes[] = $this->_orderByCount($sAttribute, $sOrder, $sCurrentModel, $aArborescence);
				continue;
			}

			$oCurrentTable = $sCurrentModel::table();
			$aRes[] = $oCurrentTable->alias . '.' .  $oCurrentTable->columnRealName($sAttribute) . ' ' . $sOrder;
        }

        $this->_sOrderBy = $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
	}

	private function _analyzeCounts($aCounts)
	{
	}

	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

	private function _orderByCount($sRelation, $sOrder, $sClass, &$aArborescence)
	{
        // $sCountAlias: `#<Relation name>`;
		$sCountAlias = '`' . $sRelation . '`';
		$sRelation   = substr($sRelation, 1);

		$oRelation   = $sClass::relation($sRelation);
		$oTable		 = $sClass::table();

		if (! isset($aArborescence[$sRelation]))
		{
			$aArborescence[$sRelation] = array();
		}
		// Go forward in arborescence.
		$aArborescence =& $aArborescence[$sRelation];

		if ($oRelation instanceof \SimpleAR\HasMany)
		{
			$sTableAlias = $oRelation->lm->alias;
			$sKey		 = $oRelation->lm->column;

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null, 'and');
		}
		elseif ($oRelation instanceof \SimpleAR\ManyMany)
		{
			$sTableAlias = $oRelation->jm->alias;
			$sKey		 = $oRelation->jm->from;

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null, 'or');
		}
		elseif ($oRelation instanceof \SimpleAR\HasOne)
		{
			$sTableAlias = $oRelation->lm->alias;
			$sKey		 = $oRelation->lm->column;

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null);
		}
		elseif ($oRelation instanceof \SimpleAR\BelongsTo)
		{
			unset($aArborescence);
			$sTableAlias = $oRelation->cm->alias;
			$sKey		 = $oRelation->cm->column;
		}

		$this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS ' . $sCountAlias;
		$this->_aGroupBy[] = $oTable->alias . '.' . $oTable->primaryKey;

		return $sCountAlias . ' ' . $sOrder;
	}

}
