<?php
namespace SimpleAR\Query;

class Update extends \SimpleAR\Query
{
	public function build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		if (isset($aOptions['conditions']))
		{
            $aConditions = \SimpleAR\Condition::parseConditionArray($aOptions['conditions']);
			$aConditions = $this->_analyzeConditions($aConditions);
            list($this->_sAnds, $aConditionValues) = \SimpleAR\Condition::arrayToSql($aConditions, false);
		}

        $this->_aColumns = $this->_oRootTable->columnRealName($aOptions['fields']);
        $this->values    = array_merge($aOptions['values'], $aConditionValues);

		$this->sql  = 'UPDATE ' . $this->_oRootTable->name . ' SET ';
        $this->sql .= implode(' = ?, ', $this->_aColumns) . ' = ?';
        $sWhere = $this->_where();
        if ($sWhere == '')
        {
            throw new \SimpleAR\Exception('Cannot execute a UPDATE query without condition.');
        }
		$this->sql .= $sWhere;

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

            $oCondition->table = $this->_oRootTable;

            // Call a user method in order to deal with complex/custom attributes.
            $sToConditionsMethod = 'to_conditions_' . $sAttribute;
            $sModel = $this->_sRootModel;
            if (method_exists($sModel, $sToConditionsMethod))
            {
                $aSubConditions = $sModel::$sToConditionsMethod($oCondition, '');
                $aSubConditions = \SimpleAR\Condition::parseConditionArray($aSubConditions);
                $aConditions[$i][1] = $this->_analyzeConditions($aSubConditions);
                continue;
            }
		}

        return $aConditions;
	}
}
