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

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build($aOptions)
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

        // If user did not specified any column.
        if (! $aColumns)
        {
            $this->sql = 'INSERT INTO `' . $sTable . '` VALUES()';
            return;
        }

        $this->sql = 'INSERT INTO `' . $sTable . '`(`' . implode('`,`', (array) $aColumns) . '`) VALUES';
        $iCount    = count($this->values);

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
