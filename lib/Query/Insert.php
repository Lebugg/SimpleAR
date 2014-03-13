<?php namespace SimpleAR\Query;
/**
 * This file contains the Insert class.
 *
 * @author Lebugg
 */

use \SimpleAR\Facades\DB;

/**
 * This class handles INSERT statements.
 */
class Insert extends \SimpleAR\Query
{
    protected static $_availableOptions = array('fields', 'values');

    protected $_table = true;
    protected $_columns;
    protected $_values;

    protected static $_components = array(
        'table',
        'columns',
        'values',
    );

    /**
     * Last inserted ID getter.
     *
     * @see Database::las_inser_id()
     *
     * @return mixed
     */
    public function insertId()
    {
        return DB::lastInsertId();
    }

    protected function _buildFields(Option\Fields $o)
    {
        $this->_columns = $o->columns;
    }

    protected function _buildValues(Option\Values $o)
    {
        $this->_values = $o->values;
    }

    protected function _compile()
    {
        // If user did not specified any column.
        // We could throw an exception, but the user may want to insert a kind
        // of "default row", that is a row with only database default values.
        if (! $this->_columns)
        {
            $this->_sql = 'INSERT INTO `' . $this->_tableName . '` VALUES()';
        }
        else
        {
            parent::_compile();
        }
    }

    protected function _compileTable()
    {
        $this->_sql = 'INSERT INTO ';

        $this->_sql .= $this->_useAlias
            ? DB::quote($this->_tableName) . ' ' . DB::quote($this->_rootAlias)
            : DB::quote($this->_tableName)
            ;
    }

    protected function _compileColumns()
    {
        $columns = $this->_useModel ? $this->_table->columnRealName($this->_columns) : $this->_columns;
        $columns = $this->columnAliasing(
            (array) $columns,
            $this->_useAlias ? $this->_rootAlias : ''
        );

        $this->_sql .= '(' . implode(',', $columns) . ')';
    }

    protected function _compileValues()
    {
        $this->_sql .= ' VALUES';

        $count = count($this->_values);

        // $this->_values is a multidimensional array. Actually, it is an array
        // of tuples.
        if (is_array($this->_values[0]))
        {
            // Tuple cardinal.
            $tupleSize = count($this->_values[0]);
            
            $tuple       = '(' . str_repeat('?,', $tupleSize - 1) . '?)';
            $this->_sql .= str_repeat($tuple . ',', $count - 1) . $tuple;

            // We also need to flatten value array.
            $this->_values = call_user_func_array('array_merge', $this->_values);
        }
        // Simple array.
        else
        {
            $this->_sql .= '(' . str_repeat('?,', $count - 1) . '?)';
        }
    }
}
