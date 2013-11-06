<?php
namespace SimpleAR;

abstract class Query
{
	protected $_sRootModel;
	protected $_oRootTable;

	protected $_sQuery;
	protected $_aValues = array();

	protected $_aConditions	= array();

	public function __construct($sRootModel)
	{
		$this->_sRootModel = $sRootModel;
		$this->_oRootTable = $sRootModel::table();
	}

	public abstract function build($aOptions);

	protected function _normalizeCondition($sAttribute, $mValue, $sOperator = '=', $sLogic = 'or', $oRelation = null)
	{
		$o = new \StdClass();

		$aAttribute = explode(',', $sAttribute);
		$o->attribute = (count($aAttribute) === 1)
			? $sAttribute
			: $aAttribute
			;

		$o->value      = $mValue;
		$o->valueCount = is_array($mValue) ? count($mValue) : 1;
		$o->operator   = $sOperator ?: ($o->valueCount === 1 ? '=' : 'IN');

		$o->logic      = $sLogic;
		$o->relation   = $oRelation;

		return $o;
	}

	/**
	 * We accept two forms of conditions:
	 * 1) Basic conditions:
	 *      array(
	 *          'my/attribute' => 'myValue',
	 *          ...
	 *      )
	 * 2) Conditions with operator:
	 *      array(
	 *          array('my/attribute', 'myOperator', 'myValue'),
	 *          ...
	 *      )
	 *
	 * Operator: =, !=, IN, NOT IN, >, <, <=, >=.
	 */
	protected function _parseCondition($mKey, $mValue)
	{
		return is_string($mKey)
			? array($mKey,      $mValue,    null)
			: array($mValue[0], $mValue[2], $mValue[1])
			;
	}

	protected function _where()
	{
		return ($this->_aAnds) ? ' WHERE ' . implode(' AND ', $this->_aAnds) : '';
	}
}
