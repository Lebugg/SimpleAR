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

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build($aConditions)
	{
        $this->_conditions($aConditions);

        if ($this->_bUseModel)
        {
            $this->_processArborescence();
            $sUsingClause = $this->_sJoin ? ' USING ' . $this->_sJoin : '';

            $this->sql .= 'DELETE ' . $this->_oRootTable->alias . ' FROM ' . $this->_oRootTable->name . ' AS ' .  $this->_oRootTable->alias;
            $this->sql .= $sUsingClause;
            $this->sql .= $this->_where();
        }
        else
        {
            $this->sql .= 'DELETE FROM ' . $this->_sRootTable;
            $this->sql .= $this->_where();
        }
	}
}
