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

	public function build($aOptions)
	{
		if (isset($aOptions['conditions']))
		{
			$this->_analyzeConditions($aOptions['conditions']);
			$this->_processConditions($this->_aConditions);
		}

		$sTable = $this->_bUseModel ? $this->_oRootTable->name : $this->_sRootTable;

		$this->_sQuery .= 'DELETE FROM ' . $sTable;
		$this->_sQuery .= $this->_where();

		var_dump($this->_sQuery);
		return array($this->_sQuery, $this->_aValues);
	}

	private function _analyzeConditions($aConditions)
	{
		foreach ($aConditions as $mConditionKey => $mConditionValue)
		{
			list($sAttribute, $mValue, $sOperator) = $this->_parseCondition($mConditionKey, $mConditionValue);
			$this->_aConditions[] = $this->_normalizeCondition($sAttribute, $mValue, $sOperator);
		}
	}

	private function _processConditions($aConditions)
	{
		foreach ($aConditions as $oCondition)
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

				$sColumn = $this->_bUseModel ?  $oTable->columnRealName($mAttribute) : $mAttribute;
				$this->_aAnds[] = $sColumn . ' ' .  $oCondition->operator . ' ' . $sConditionValueString;

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
						$sColumn = $this->_bUseModel ?  $oTable->columnRealName($mAttribute) : $mAttribute;

						$aTmp[] = $sColumn . ' ' . $oCondition->operator . ' ?';
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
}
