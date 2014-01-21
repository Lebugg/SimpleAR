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
    protected static $_bIsCriticalQuery = true;

    protected static $_options = array('conditions');

    protected function _compile()
    {
		$this->_sSql = $this->_context->useAlias
            ? 'DELETE ' . $this->_context->rootTable->alias . ' FROM ' .  $this->_context->rootTableName . ' AS ' .  $this->_context->rootTable->alias
            : 'DELETE FROM ' . $this->_context->rootTableName
            ;

        $sJoin = $this->_processArborescence();

        // Equivalent FROM clause for DELETE queries.
        $this->_sSql .= $sJoin ? ' USING ' . $sJoin : '';
        $this->_sSql .= $this->_where();
	}
}
