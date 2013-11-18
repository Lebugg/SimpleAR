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
		$this->sql .= $this->_valuesStatement($this->values);

        $this->values = call_user_func_array('array_merge', $this->values);
	}

    private function _valuesStatement($aValues)
    {
        $iCount = count($aValues);

        // $aValues is a multidimensional array. Actually it is a array of
        // tuples.
        if (is_array($aValues[0]))
        {
            // Tuple cardinal.
            $iTupleSize = count($aValues[0]);
            
            $sTuple = '(' . str_repeat('?,', $iTupleSize - 1) . '?)';
            $sRes   = str_repeat($sTuple . ',', $iCount - 1) . $sTuple;
        }
        // Simple array.
        else
        {
            $sRes = '(' . str_repeat('?,', $iCount - 1) . '?)';
        }

        return $sRes;
    }
}
