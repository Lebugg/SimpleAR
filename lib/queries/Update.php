<?php
namespace SimpleAR\Query;
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */

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
    protected static $_bIsCriticalQuery = true;

    /**
     * Never use aliases.
     *
     * @var bool
     */
    protected $_bUseAlias = false;

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

        $this->_aColumns = $this->_oRootTable->columnRealName($aOptions['fields']);
        $this->values    = array_merge($aOptions['values'], $this->values);

		$this->sql  = 'UPDATE ' . $this->_oRootTable->name . ' SET ';
        $this->sql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->sql .= $this->_sWhere;
	}
}
