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
class Delete extends \SimpleAR\Query\Where
{
    /**
     * This query is critical.
     *
     * @var bool true
     */
    protected static $_isCriticalQuery = true;

    protected static $_availableOptions = array('conditions');

    protected $_from = true;

    protected static $_components = array(
        'from',
        'where'
    );

    protected function _compileFrom()
    {
        $this->_sql = 'DELETE ';

        $qName  = DB::quote($this->_tableName);
        $qAlias = DB::quote($this->_rootAlias);

		$this->_sql .= $this->_useAlias
            ? $qAlias . ' FROM ' . $qName . ' AS ' . $qAlias
            : 'FROM `' . $qName . '`'
            ;

        // Equivalent JOIN clause for DELETE queries.
        if ($this->_arborescence
            && $using = $this->_arborescence->toSql())
        {
            $this->_sql .= ' USING ' . $using;
        }
    }
}
