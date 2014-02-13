<?php
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

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

    protected static $_options = array('conditions');

    protected function _compile()
    {
		$this->_sql = $this->_context->useAlias
            ? 'DELETE `' . $this->_context->rootTableAlias . '` FROM `' .  $this->_context->rootTableName . '` AS `' .  $this->_context->rootTableAlias . '`'
            : 'DELETE FROM `' . $this->_context->rootTableName . '`'
            ;

        $join = $this->_join();

        // Equivalent FROM clause for DELETE queries.
        $this->_sql .= $join ? ' USING ' . $join : '';
        $this->_sql .= $this->_where();
	}
}
