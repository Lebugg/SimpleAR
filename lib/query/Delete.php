<?php
namespace SimpleAR\Query;

class Delete extends \SimpleAR\Query
{
	private $_bUseModel  = true;
	private $_sRootTable = '';

	public function __construct($sRootModel)
	{
		if (class_exists($sRootModel))
		{
			parent::__construct($sRootModel);
		}
		else
		{
			$this->_bUseModel  = false;
			$this->_sRootTable = $sRootModel;
		}
	}

	public function build($aConditions)
	{
        $aConditions = \SimpleAR\Condition::parseConditionArray($aConditions);
        $aConditions = $this->_analyzeConditions($aConditions);
        list($this->_sAnds, $this->_aValues) = \SimpleAR\Condition::arrayToSql($aConditions, false, $this->_bUseModel);

		$sTable = $this->_bUseModel ? $this->_oRootTable->name : $this->_sRootTable;

		$this->_sQuery .= 'DELETE FROM ' . $sTable;
        $sWhere = $this->_where();
        if ($sWhere == '')
        {
            throw new \SimpleAR\Exception('Cannot execute a DELETE query without condition.');
        }
		$this->_sQuery .= $sWhere;

		return array($this->_sQuery, $this->_aValues);
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

            if ($this->_bUseModel)
            {
                $oCondition->table = $this->_oRootTable;

                // Call a user method in order to deal with complex/custom attributes.
                $sToConditionsMethod = 'to_conditions_' . $sAttribute;
                $sModel = $this->_sRootModel;
                if (method_exists($sModel, $sToConditionsMethod))
                {
                    $aSubConditions = $sModel::$sToConditionsMethod($oCondition, $sArborescence);
                    $aSubConditions = \SimpleAR\Condition::parseConditionArray($aSubConditions);
                    $aConditions[$i][1] = $this->_analyzeConditions($aSubConditions);
                    continue;
                }
            }
		}

        return $aConditions;
	}
}
