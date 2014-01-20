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

    protected static $_aOptions = array('conditions', 'fields', 'values');

    public function fields(array $fields)
    {
        $this->_aColumns = array_merge($this->_aColumns, $fields);
    }

    public function values(array $values)
    {
        $this->_aValues = array_merge($this->_aValues, $values);
    }

    protected function _compile()
    {
		$this->_sSql = $this->_oContext->useAlias
            ? 'UPDATE ' . $this->_oContext->rootTableName . ' ' .  $this->_oContext->rootTable->alias . ' SET '
            : 'UPDATE ' . $this->_oContext->rootTableName . ' SET '
            ;

        // Not used?
        //$sJoin = $this->_processArborescence();

        $this->_sSql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->_sSql .= $this->_where();
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);
    }
}
