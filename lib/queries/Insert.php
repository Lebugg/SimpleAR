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
            $this->_sql = 'INSERT INTO `' . $this->_context->rootTableName . '` VALUES()';
            return;
        }

        $this->_sql = $this->_context->useAlias
            ? 'INSERT INTO `' . $this->_context->rootTableName . '` `' . $this->_context->rootTableAlias . '`'
            : 'INSERT INTO `' . $this->_context->rootTableName . '`'
            ;

        $this->_sql .= '(' . implode(',', (array) $this->_columns) . ') VALUES';
        $iCount       = count($this->_values);

        // $this->_values is a multidimensional array. Actually, it is an array of
        // tuples.
        if (is_array($this->_values[0]))
        {
            // Tuple cardinal.
            $iTupleSize = count($this->_values[0]);
            
            $sTuple     = '(' . str_repeat('?,', $iTupleSize - 1) . '?)';
            $this->_sql .= str_repeat($sTuple . ',', $iCount - 1) . $sTuple;

            // We also need to flatten value array.
            $this->_values = call_user_func_array('array_merge', $this->_values);
        }
        // Simple array.
        else
        {
            $this->_sql .= '(' . str_repeat('?,', $iCount - 1) . '?)';
        }
	}

    protected function _values(Option $option)
    {
        $this->_values = array_merge($this->_values, $option->build());
    }

    protected function _fields(Option $option)
    {
        $this->_columns = array_merge($this->_columns, $option->build());
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        $this->_context->useAlias = false;
    }

}
