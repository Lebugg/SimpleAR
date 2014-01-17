<?php
/**
 * This file contains the Insert class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles INSERT statements.
 */
class Insert extends \SimpleAR\Query
{
    protected static $_aOptions = array('fields', 'values');

    /**
     * Last inserted ID getter.
     *
     * @see Database::lastInsertId()
     *
     * @return mixed
     */
    public function insertId()
    {
        return self::$_oDb->lastInsertId();
    }

	protected function _compile()
	{
        // If user did not specified any column.
        if (! $this->_aColumns)
        {
            $this->_sSql = 'INSERT INTO `' . $sTable . '` VALUES()';
            return;
        }

        if ($this->_bUseModel)
        {
            $sTable   = $this->_oRootTable->name;
        }
        else
        {
            $sTable   = $this->_sRootTable;
        }

        $this->_sSql = 'INSERT INTO `' . $sTable . '`(`' . implode('`,`', (array) $this->_aColumns) . '`) VALUES';
        $iCount    = count($this->_aValues);

        // $this->_aValues is a multidimensional array. Actually, it is an array of
        // tuples.
        if (is_array($this->_aValues[0]))
        {
            // Tuple cardinal.
            $iTupleSize = count($this->_aValues[0]);
            
            $sTuple     = '(' . str_repeat('?,', $iTupleSize - 1) . '?)';
            $this->_sSql .= str_repeat($sTuple . ',', $iCount - 1) . $sTuple;

            // We also need to flatten value array.
            $this->_aValues = call_user_func_array('array_merge', $this->_aValues);
        }
        // Simple array.
        else
        {
            $this->_sSql .= '(' . str_repeat('?,', $iCount - 1) . '?)';
        }
	}

    public function fields($aFields)
    {
        $aFields = (array) $aFields;

        if ($this->_bUseModel)
        {
            $this->_aColumns = $this->_oRootTable->columnRealName($aFields);
            $sTable   = $this->_oRootTable->name;
        }
        else
        {
            $this->_aColumns = $aFields;
            $sTable   = $this->_sRootTable;
        }

    }

    public function values($aValues)
    {
        $this->_aValues = array_merge((array) $aValues, $this->_aValues);
    }

}
