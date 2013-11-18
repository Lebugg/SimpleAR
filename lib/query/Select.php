<?php
namespace SimpleAR\Query;

class Select extends \SimpleAR\Query
{
	private $_aArborescence = array();
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
            $aConditions = \SimpleAR\Condition::parseConditionArray($aOptions['conditions']);
			$aConditions = $this->_analyzeConditions($aConditions);
            list($this->_sAnds, $this->values) = \SimpleAR\Condition::arrayToSql($aConditions);
		}

		if (isset($aOptions['order_by']))
		{
			$this->_analyzeOrderBy($aOptions['order_by']);
		}

		if (isset($aOptions['counts']))
		{
			$this->_analyzeCounts($aOptions['counts']);
		}

		$this->sql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias . ' ' . $this->_joinArborescenceToSql($this->_aArborescence, $this->_sRootModel);
		$this->sql .= $this->_where();
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

		return array($this->sql, $this->values);
	}

	public function buildCount($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		if (isset($aOptions['conditions']))
		{
            $aConditions = \SimpleAR\Condition::parseConditionArray($aOptions['conditions']);
			$aConditions = $this->_analyzeConditions($aConditions);
            list($this->_sAnds, $this->values) = \SimpleAR\Condition::arrayToSql($aConditions);
		}

		$this->sql  = 'SELECT COUNT(*)';
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias . ' ' . $this->_joinArborescenceToSql($this->_aArborescence, $this->_sRootModel);
		$this->sql .= $this->_where();

		return array($this->sql, $this->values);
	}

	private function _analyzeConditions($aConditions)
	{
        for ($i = 0, $iCount = count($aConditions) ; $i < $iCount ; ++$i)
        {
            $sLogicalOperator = $aConditions[$i][0];
            $mItem            = $aConditions[$i][1];

            // Group of conditions.
            if (is_array($mItem))
            {
                $aConditions[$i][1] = $this->_analyzeConditions($mItem);
                continue;
            }

            // It necessarily is a Condition instance.

            $oCondition = $mItem;
            $sAttribute = $oCondition->attribute;
            $sOperator  = $oCondition->operator;
            $mValue     = $oCondition->value;

            // We want to save arborescence without attribute name for later
            // use.
            $sArborescence = strrpos($sAttribute, '/') ? substr($sAttribute, 0, strrpos($sAttribute, '/') + 1) : '';

            // Explode relation arborescence.
            $aPieces = explode('/', $sAttribute);

            // Attribute name only.
			$sAttribute = array_pop($aPieces);

			// Add related model(s) into join arborescence.
			$aArborescence =& $this->_aArborescence;
			$sCurrentModel =  $this->_sRootModel;
            $oRelation     =  null;
			foreach ($aPieces as $sRelation)
			{
				$oRelation = $sCurrentModel::relation($sRelation);
				if (! isset($aArborescence[$sRelation]))
				{
					$aArborescence[$sRelation] = array();
				}

				// Go forward in arborescence.
				$aArborescence =& $aArborescence[$sRelation];
				$sCurrentModel =  $oRelation->linkedModelClass();
			}

            // Let the condition know which relation it is associated with.
			$oCondition->relation  = $oRelation;
            $oCondition->table     = $sCurrentModel::table();
            // Remove arborescence from Condition attribute string.
            $oCondition->attribute = $sAttribute;

            // Call a user method in order to deal with complex/custom attributes.
            $sToConditionsMethod = 'to_conditions_' . $sAttribute;
            if (method_exists($sCurrentModel, $sToConditionsMethod))
            {
                $aSubConditions = $sCurrentModel::$sToConditionsMethod($oCondition, $sArborescence);

                /**
                 * to_conditions_* may return nothing when they directly modify
                 * the Condition object.
                 */
                if ($aSubConditions)
                {
                    $aSubConditions     = \SimpleAR\Condition::parseConditionArray($aSubConditions);
                    $aConditions[$i][1] = $this->_analyzeConditions($aSubConditions);
                }
                continue;
            }

            if ($oRelation !== null)
            {
                // Add actual attribute to arborescence.
                $aArborescence['@'][] = $oCondition;
            }
		}

        return $aConditions;
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
				$sCurrentModel =  $oRelation->linkedModelClass();
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

	private function _joinArborescenceToSql($aArborescence, $sCurrentModel)
	{
		$sRes = '';

		foreach ($aArborescence as $sRelationName => $aValues)
		{
			$oRelation = $sCurrentModel::relation($sRelationName);

			// If there are values for this relation, join it as last.
			if (isset($aValues['@']))
			{
				$sRes .= $oRelation->joinAsLast($aValues['@']);
				unset($aValues['@']);
			}

			// If relation arborescence continues, process it.
			if ($aValues)
			{
				$sRes .= $oRelation->joinLinkedModel();
				$sRes .= $this->_joinArborescenceToSql($aValues, $oRelation->linkedModelClass());
			}
		}

		return $sRes;
	}

	private function _orderByCount($sRelation, $sOrder, $sClass, &$aArborescence)
	{
		$sRelation   = substr($sRelation, 1);
		$sCountAlias = 'COUNT_' . $sRelation;

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
			$sTableAlias = $oRelation->linkedModelTableAlias();
			$sKey		 = $oRelation->keyTo(TRUE);

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null, 'and');
		}
		elseif ($oRelation instanceof \SimpleAR\ManyMany)
		{
			$sTableAlias = $oRelation->joinTableAlias();
			$sKey		 = $oRelation->joinKeyFrom();

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null, 'or');
		}
		elseif ($oRelation instanceof \SimpleAR\HasOne)
		{
			$sTableAlias = $oRelation->linkedModelTableAlias();
			$sKey		 = $oRelation->keyTo(TRUE);

			$aArborescence['@'][] = new \SimpleAR\Condition('id', null, null);
		}
		elseif ($oRelation instanceof \SimpleAR\BelongsTo)
		{
			unset($aArborescence);
			$sTableAlias = $oRelation->currentModelTableAlias();
			$sKey		 = $oRelation->keyFrom(TRUE);
		}

		$this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS ' . $sCountAlias;
		$this->_aGroupBy[] = $oTable->alias . '.' . $oTable->primaryKey;

		return $sCountAlias . ' ' . $sOrder;
	}

}
