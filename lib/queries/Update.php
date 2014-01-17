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

    protected static $_aOptions = array('conditions', 'fields', 'values');

    protected function _compile()
    {
        $this->_where();

		$this->_sSql  = 'UPDATE ' . $this->_oRootTable->name . ' SET ';
        $this->_sSql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->_sSql .= $this->_sWhere;
    }

    public function fields(array $aFields)
    {
        $this->_aColumns = $this->_oRootTable->columnRealName($aFields);
    }

    public function values(array $aValues)
    {
        $this->_aValues = array_merge($aValues, $this->_aValues);
    }

}
