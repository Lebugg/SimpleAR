<?php
namespace SimpleAR\Query;

class Insert extends \SimpleAR\Query
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
        $this->values = $aOptions['values'];

        $this->_aColumns = $this->_bUseModel
            ? $this->_oRootTable->columnRealName($aOptions['fields'])
            : $aOptions['fields']
            ;

		$sTable = $this->_bUseModel ? $this->_oRootTable->name : $this->_sRootTable;

		$this->sql .= 'INSERT INTO ' . $sTable . '(`' . implode('`,`', $this->_aColumns) . '`) VALUES';
		$this->sql .= \SimpleAR\Condition::rightHandSide($this->values);

		return array($this->sql, $this->values);
	}
}
