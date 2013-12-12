<?php
namespace SimpleAR\Query;

class Update extends \SimpleAR\Query\Where
{
    protected static $_isCriticalQuery = true;
    protected $_bUseAlias = false;

	public function build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

        $this->_aColumns = $this->_oRootTable->columnRealName($aOptions['fields']);
        $this->values    = array_merge($aOptions['values'], $this->values);

		$this->sql  = 'UPDATE ' . $this->_oRootTable->name . ' SET ';
        $this->sql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->sql .= $this->_sWhere;
	}
}
