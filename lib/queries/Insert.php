<?php
namespace SimpleAR\Query;

class Insert extends \SimpleAR\Query
{
	public function build($aOptions)
	{
        $this->values = $aOptions['values'];

        if ($this->_bUseModel)
        {
            $aColumns = $this->_oRootTable->columnRealName($aOptions['fields']);
            $sTable   = $this->_oRootTable->name;
        }
        else
        {
            $aColumns = $aOptions['fields'];
            $sTable   = $this->_sRootTable;
        }

        // INTO clause.
        $this->sql  = 'INSERT INTO ' . $sTable . '(`' . implode('`,`', (array) $aColumns) . '`) VALUES';

        // VALUES clause.
        $iCount = count($this->values);

        // $this->values is a multidimensional array. Actually, it is an array of
        // tuples.
        if (is_array($this->values[0]))
        {
            // Tuple cardinal.
            $iTupleSize = count($this->values[0]);
            
            $sTuple     = '(' . str_repeat('?,', $iTupleSize - 1) . '?)';
            $this->sql .= str_repeat($sTuple . ',', $iCount - 1) . $sTuple;

            // We also need to flatten value array.
            $this->values = call_user_func_array('array_merge', $this->values);
        }
        // Simple array.
        else
        {
            $this->sql .= '(' . str_repeat('?,', $iCount - 1) . '?)';
        }
	}
}
