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
        // We could throw an exception, but the user may want to insert a kind
        // of "default row", that is a row with only database default values.
        if (! $this->_aColumns)
        {
            $this->_sSql = 'INSERT INTO `' . $this->_oContext->rootTableName . '` VALUES()';
            return;
        }

        $this->_sSql = 'INSERT INTO `' . $this->_oContext->rootTableName . '`(`' . implode('`,`', (array) $this->_aColumns) . '`) VALUES';
        $iCount      = count($this->_aValues);

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

        $this->_aColumns = $this->_oContext->useModel
            ? $this->_oContext->rootTable->columnRealName($aFields)
            : $aFields
            ;
    }

    public function values($aValues)
    {
        $this->_aValues = array_merge($this->_aValues, (array) $aValues);
    }

}
