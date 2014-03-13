<?php namespace SimpleAR\Query;
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */

use \SimpleAR\Facades\DB;

/**
 * This class handles UPDATE statements.
 */
class Update extends \SimpleAR\Query\Where
{
    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    protected static $_availableOptions = array('conditions', 'fields', 'values');

    protected $_table = true;
    protected $_set;

    protected static $_components = array(
        'table',
        'set',
        'where',
    );

    protected function _buildFields(Option\Fields $o)
    {
        $this->_set = $o->columns;
    }

    protected function _buildValues(Option\Values $o)
    {
        $this->_values = $o->values;
    }


    protected function _compileTable()
    {
        $this->_sql = 'UPDATE ';

        $this->_sql .= $this->_useAlias
            ? DB::quote($this->_tableName) . ' ' . DB::quote($this->_rootAlias)
            : DB::quote($this->_tableName)
            ;
    }

    protected function _compileSet()
    {
        $columns = $this->_useModel ? $this->_table->columnRealName($this->_set) : $this->_set;
        $columns = $this->columnAliasing(
            (array) $columns,
            $this->_useAlias ? $this->_rootAlias : ''
        );

        $this->_sql .= ' SET ' . implode(' = ?, ', $columns) . ' = ?';
    }
}
