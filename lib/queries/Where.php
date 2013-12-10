<?php
namespace SimpleAR\Query;

abstract class Where extends \SimpleAR\Query
{
	protected $_aArborescence = array();
    protected $_sJoin         = '';
    protected $_sWhere        = '';

    protected $_aJoinedTables = array();

    /**
     *
     * @return array
     *      To be used as `list($aArborescence, $oLastRelation) = $this->_addToArborescence(<>);`
     */
    protected function _addToArborescence($aRelationNames)
    {
        if (!$aRelationNames) { return; }

        // Add related model(s) in join arborescence.
        $aArborescence =& $this->_aArborescence;
        $sCurrentModel =  $this->_sRootModel;
        $oRelation     =  null;

        foreach ($aRelationNames as $sRelation)
        {
            $oRelation = $sCurrentModel::relation($sRelation);
            if (! isset($aArborescence[$sRelation]))
            {
                $aArborescence[$sRelation] = array();
            }

            // Go forward in arborescence.
            $aArborescence = &$aArborescence[$sRelation];
            $sCurrentModel = $oRelation->lm->class;
        }

		if ($oRelation instanceof \SimpleAR\HasMany)
		{
			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null, 'and');
		}
		elseif ($oRelation instanceof \SimpleAR\ManyMany)
		{
			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null, 'or');
		}
		elseif ($oRelation instanceof \SimpleAR\HasOne)
		{
			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('id', null, null);
		}
        else
        {
			$aArborescence['_CONDITIONS_'][] = new \SimpleAR\Condition('notid', null, null);
        }

        return array(&$aArborescence, $oRelation);
    }

    protected function _arborescenceToSql()
    {
        $this->_sJoin = $this->_joinArborescenceToSql($this->_aArborescence, $this->_sRootModel);
    }

    protected function _where($aConditions)
    {
        // Transform user syntax formatted condition array into a object
        // formatted array.
        $aConditions = \SimpleAR\Condition::parseConditionArray($aConditions);

        // Arborescence feature is only available when using models (it is based
        // on model relationships).
        if ($this->_bUseModel)
        {
            $aConditions = $this->_extractArborescenceFromConditions($aConditions);
        }

        // We made all wanted treatments; get SQL out of Condition array.
        // We update values because Condition::arrayToSql() will flatten them in
        // order to bind them to SQL string with PDO.
        list($sSql, $this->values) = \SimpleAR\Condition::arrayToSql($aConditions, $this->_bUseAlias, $this->_bUseModel);

		$this->_sWhere = ($sSql) ? ' WHERE ' . $sSql : '';
    }

	private function _extractArborescenceFromConditions($aConditions)
	{
        for ($i = 0, $iCount = count($aConditions) ; $i < $iCount ; ++$i)
        {
            $sLogicalOperator = $aConditions[$i][0];
            $mItem            = $aConditions[$i][1];

            // Group of conditions.
            if (is_array($mItem))
            {
                $aConditions[$i][1] = $this->_extractArborescenceFromConditions($mItem);
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
            list($aArborescence, $oRelation) = $this->_addToArborescence($aPieces);
            $sCurrentModel = $oRelation ? $oRelation->lm->class : $this->_sRootModel;

            // Let the condition know which relation it is associated with.
			$oCondition->relation  = $oRelation;
            $oCondition->table     = clone $sCurrentModel::table();
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
                    $aConditions[$i][1] = $this->_extractArborescenceFromConditions($aSubConditions);
                }
                continue;
            }

            if ($oRelation !== null)
            {
                // Add actual attribute to arborescence.
                $aArborescence['_CONDITIONS_'][] = $oCondition;
            }
		}

        return $aConditions;
	}

	private function _joinArborescenceToSql($aArborescence, $sCurrentModel, $iDepth = 1)
	{
		$sRes = '';

        // Construct joined table array according to depth.
        if (! isset($this->_aJoinedTables[$iDepth]))
        {
            $this->_aJoinedTables[$iDepth] = array();
        }

		foreach ($aArborescence as $sRelationName => $aValues)
		{
			$oRelation = $sCurrentModel::relation($sRelationName);
            $sTable    = $oRelation->lm->table;

			// If relation arborescence continues, process it.
			if ($aValues)
			{
                // Join it if not done yet.
                if (! in_array($sTable, $this->_aJoinedTables[$iDepth]))
                {
                    $sRes .= $oRelation->joinLinkedModel($iDepth);

                    // Add it to joined tables array.
                    $this->_aJoinedTables[$iDepth][] = $sTable;
                }

                unset($aValues['_CONDITIONS_']);
				$sRes .= $this->_joinArborescenceToSql($aValues, $oRelation->lm->class, $iDepth + 1);
			}
            // If there are conditions to apply on linked model, we may have to join it.
			elseif (isset($aValues['_CONDITIONS_']))
			{
                // Already joined? Don't process it.
                if (isset($this->_aJoinedTables[$iDepth][$oRelation->lm->table]))
                {
				    $sRes .= $oRelation->joinAsLast($aValues['_CONDITIONS_'], $iDepth);
                }

                unset($aValues['_CONDITIONS_']);
			}

		}

		return $sRes;
	}
}
