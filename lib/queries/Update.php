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
	protected function _build(array $aOptions)
	{
		if (isset($aOptions['conditions']))
		{
            $this->_conditions($aOptions['conditions']);
            $this->_where();
		}

        $this->_aColumns = $this->_oRootTable->columnRealName($aOptions['fields']);
        $this->values    = array_merge($aOptions['values'], $this->values);
	}

    protected function _compile()
    {
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		$this->sql  = 'UPDATE ' . $this->_oRootTable->name . ' SET ';
        $this->sql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->sql .= $this->_sWhere;
    }
}
