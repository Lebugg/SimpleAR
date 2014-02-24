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
    protected static $_options = array('fields', 'values');

    protected $_table;
    protected $_columns;
    protected $_values;

    protected static $_components = array(
        'table',
        'columns',
        'values',
    );

    public function __construct($root)
    {
        parent::__construct($root);

        $this->_table = $root;
    }

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

    protected function _compile()
    {
        // If user did not specified any column.
        // We could throw an exception, but the user may want to insert a kind
        // of "default row", that is a row with only database default values.
        if (! $this->_columns)
        {
            $this->_sql = 'INSERT INTO `' . $this->_context->rootTableName . '` VALUES()';
        }
        else
        {
            parent::_compile();
        }
    }

    protected function _compileTable()
    {
        $this->_sql = 'INSERT INTO ';

        $c = $this->_context;
        $this->_sql .= $c->useAlias
            ? '`' . $c->rootTableName . '` `' . $c->rootTableAlias . '`'
            : '`' . $c->rootTableName . '`'
            ;
    }

    protected function _compileColumns()
    {
        $this->_sql .= '(' . implode(',', $this->_columns) . ')';
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

    protected function _handleOption(Option $option)
    {
        switch (get_class($option))
        {
            case 'SimpleAR\Query\Option\Fields':
                $this->_columns = $option->columns;
                break;
            case 'SimpleAR\Query\Option\Values':
                $this->_values = $option->values;
                break;
            default:
                parent::_handleOption($option);
        }
    }

    protected function _initContext($root)
    {
        parent::_initContext($root);

        $this->_context->useAlias = false;
    }

}
