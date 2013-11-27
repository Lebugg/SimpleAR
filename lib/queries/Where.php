<?php
namespace SimpleAR\Query;

abstract class Where extends \SimpleAR\Query
{
	protected $_aArborescence = array();
    protected $_sJoin         = '';
    protected $_sWhere        = '';

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
				$sCurrentModel =  $oRelation->lm->class;
			}

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
                $aArborescence['@'][] = $oCondition;
            }
		}

        return $aConditions;
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
				$sRes .= $this->_joinArborescenceToSql($aValues, $oRelation->lm->class);
			}
		}

		return $sRes;
	}
}
