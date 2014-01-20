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

    protected static $_aOptions = array('conditions');

    protected function _compile()
    {
		$this->_sSql = $this->_oContext->useAlias
            ? 'DELETE ' . $this->_oContext->rootTable->alias . ' FROM ' .  $this->_oContext->rootTableName . ' AS ' .  $this->_oContext->rootTable->alias
            : 'DELETE FROM ' . $this->_oContext->rootTableName
            ;

        $this->_processArborescence();

        // Equivalent FROM clause for DELETE queries.
        $this->_sSql .= $this->_sJoin ? ' USING ' . $this->_sJoin : '';
        $this->_sSql .= $this->_where();
	}
}
