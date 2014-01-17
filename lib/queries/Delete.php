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
        if ($this->_bUseModel)
        {
            $this->_processArborescence();
            $sUsingClause = $this->_sJoin ? ' USING ' . $this->_sJoin : '';

            $this->_sSql .= 'DELETE ' . $this->_oRootTable->alias . ' FROM ' . $this->_oRootTable->name . ' AS ' .  $this->_oRootTable->alias;
            $this->_sSql .= $sUsingClause;
            $this->_sSql .= $this->_where();
        }
        else
        {
            $this->_sSql .= 'DELETE FROM ' . $this->_sRootTable;
            $this->_sSql .= $this->_where();
        }
	}
}
