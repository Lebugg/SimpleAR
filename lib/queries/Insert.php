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
    protected static $_options = array('fields', 'values');

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
        if (! $this->_columns)
        {
            $this->_sSql = 'INSERT INTO `' . $this->_context->rootTableName . '` VALUES()';
            return;
        }

        $this->_sSql = $this->_context->useAlias
            ? 'INSERT INTO `' . $this->_context->rootTableName . '` `' . $this->_context->rootTableAlias . '`'
            : 'INSERT INTO `' . $this->_context->rootTableName . '`'
            ;

        $this->_sSql .= '(' . implode(',', (array) $this->_columns) . ') VALUES';
        $iCount       = count($this->_aValues);

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

    public function fields(array $fields)
    {
        $this->_columns = array_merge($this->_columns, $fields);
    }

    public function values(array $values)
    {
        $this->_aValues = array_merge($this->_aValues, $values);
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        $this->_context->useAlias = false;
    }

}
