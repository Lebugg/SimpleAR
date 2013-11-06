<?php
namespace SimpleAR\Query;

class Select extends Query
{
	private $_aArborescence = array();
	private $_aSelects		= array();
	private $_aAnds   		= array();
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
			$this->_analyzeConditions($aOptions['conditions']);
			$this->_processConditions($this->_aConditions);
		}

		if (isset($aOptions['order_by']))
		{
			$this->_analyzeOrderBy($aOptions['order_by']);
		}

		if (isset($aOptions['counts']))
		{
			$this->_analyzeCounts($aOptions['counts']);
		}

		$this->_sQuery  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->_sQuery .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias . ' ' . $this->_joinArborescenceToSql($this->_aArborescence, $this->_sRootModel);
		$this->_sQuery .= $this->_where();
		$this->_sQuery .= $this->_groupBy();
		$this->_sQuery .= $this->_sOrderBy;

		if (isset($aOptions['limit']))
		{
			$this->_sQuery .= ' LIMIT ' . $aOptions['limit'];
		}

		if (isset($aOptions['offset']))
		{
			$this->_sQuery .= ' OFFSET ' . $aOptions['offset'];
		}

		var_dump($this->_sQuery);
		return array($this->_sQuery, $this->_aValues);
	}

	private function _analyzeConditions($aConditions)
	{
		foreach ($aConditions as $mConditionKey => $mConditionValue)
		{
			list($sAttribute, $mValue, $sOperator) = $this->_parseCondition($mConditionKey, $mConditionValue);

            $aPieces = explode('/', $sAttribute);
            $iCount  = count($aPieces);

            // Empty string.
            if ($iCount === 0)
            {
                throw new Exception('Invalid condition attribute: attribute is empty.');
            }

			// Attribute of root model.
			if ($iCount === 1)
			{
				$this->_aConditions[] = $this->_normalizeCondition($sAttribute, $mValue, $sOperator, 'or', null);
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

			$oCondition = $this->_normalizeCondition($sAttribute, $mValue, $sOperator, 'or', $oRelation);

			// Add actual attribute to arborescence.
			$aArborescence['@'][] = $oCondition;

			// And, of course, add condition to the list of conditions.
			$this->_aConditions[] = $oCondition;
		}
	}

	private function _analyzeOrderBy($aOrderBy)
	{
        $aRes   	= array();
		$sRootAlias = $this->_oRootTable->alias;

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge($this->_oRootTable->orderBy, $aOrderBy) as $sAttribute => $sOrder)
        {
            $aPieces = explode('/', $sAttribute);
            $iCount  = count($aPieces);

            // Empty string.
            if ($iCount === 0)
            {
                throw new Exception('Invalid ORDER BY attribute: attribute is empty.');
            }

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
		$sCountAlias = 'COUNT_' . $sRelation;
		$sRelation   = substr($sRelation, 1);

		$oRelation   = $sClass::relation($sRelation);
		$oTable		 = $sClass::table();

		if (! isset($aArborescence[$sRelation]))
		{
			$aArborescence[$sRelation] = array();
		}
		// Go forward in arborescence.
		$aArborescence =& $aArborescence[$sRelation];

		if ($oRelation instanceof HasMany)
		{
			$sTableAlias = $oRelation->linkedModelTableAlias();
			$sKey		 = $oRelation->keyTo(TRUE);

			$aArborescence['@'][] = $this->_normalizeCondition('id', null, null, 'and', null);
		}
		elseif ($oRelation instanceof ManyMany)
		{
			$sTableAlias = $oRelation->joinTableAlias();
			$sKey		 = $oRelation->joinKeyFrom();

			$aArborescence['@'][] = $this->_normalizeCondition('id', null, null, 'or', null);
		}
		elseif ($oRelation instanceof HasOne)
		{
			$sTableAlias = $oRelation->linkedModelTableAlias();
			$sKey		 = $oRelation->keyTo(TRUE);

			$aArborescence['@'][] = $this->_normalizeCondition('id', null, null, null, null);
		}
		elseif ($oRelation instanceof BelongsTo)
		{
			unset($aArborescence);
			$sTableAlias = $oRelation->currentModelTableAlias();
			$sKey		 = $oRelation->keyFrom(TRUE);
		}

		$this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS ' . $sCountAlias;
		$this->_aGroupBy[] = $oTable->alias . '.' . $oTable->primaryKey;

		return $sCountAlias . ' ' . $sOrder;
	}

	private function _processConditions($aConditions)
	{
		foreach ($aConditions as $oCondition)
		{
			
			// Condition is made on a root model attribute.
			if ($oCondition->relation === null)
			{
				$this->_processConditionOnRootModelAttribute($oCondition);
				continue;
			}

			$this->_aAnds[] = $oCondition->relation->condition($oCondition);

			if (is_array($oCondition->value))
			{
				$this->_aValues = array_merge($this->_aValues, $oCondition->value);
			}
			else
			{
				$this->_aValues[] = $oCondition->value;
			}
		}
	}

	private function _processConditionOnRootModelAttribute($oCondition)
	{
		$oTable		= $this->_oRootTable;
		$mAttribute = $oCondition->attribute;
		$mValue		= $oCondition->value;

		$mAttribute = explode(',', $mAttribute);

		// We check if given attribute is a compound attribute (a couple, for example).
		if (count($mAttribute) === 1) // Simple attribute.
		{
			$mAttribute = $mAttribute[0];

			// Construct right hand part of the condition.
			if (is_array($mValue))
			{
				$sConditionValueString = '(' . str_repeat('?,', $oCondition->valueCount - 1) . '?)';
			}
			else
			{
				$sConditionValueString = '?';
			}

			$this->_aAnds[] = $oTable->alias . '.' .  $oTable->columnRealName($mAttribute) . ' ' .  $oCondition->operator . ' ' . $sConditionValueString;

			if ($oCondition->valueCount === 1)
			{
				$this->_aValues[] = $mValue;
			}
			else
			{
				$this->_aValues = array_merge($this->_aValues, $mValue);
			}
		}
		else // Compound attribute;
		{
			$aTmp      = array();
			$aTmp2     = array();

			foreach ($mValue as $aTuple)
			{
				foreach ($mAttribute as $i => $sAttribute)
				{
					$sColumn = $oTable->columnRealName($sAttribute);

					$aTmp[] = "{$oTable->alias}.{$sColumn} {$oCondition->operator} ?";
					$this->_aValues[] = $aTuple[$i];
				}

				$aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
				$aTmp    = array();
			}

			// The 'OR' simulates a IN statement for compound keys.
			$this->_aAnds[] = '(' . implode(' OR ', $aTmp2) . ')';
		}
	}

}
