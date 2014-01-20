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

    public function fields($aFields)
    {
        $a = (array) $aFields;

        // We have to translate attribute to columns.
        if ($this->_oContext->useModel)
        {
            // We cast into array because columnRealName can return a string
            // even if we gave it an array.
            $a = (array) $this->_oContext->rootTable->columnRealName($a);
        }

        // We have to use table alias.
        if ($this->_oContext->useAlias)
        {
            $a = self::columnAliasing($a);
        }

        $this->_aColumns = $a;
    }

    public function values($aValues)
    {
        $this->_aValues = array_merge($this->_aValues, (array) $aValues);
    }

    protected function _compile()
    {
		$this->_sSql = $this->_oContext->useAlias
            ? 'UPDATE ' . $this->_oContext->rootTableName . ' ' .  $this->_oContext->rootTable->alias . ' SET '
            : 'UPDATE ' . $this->_oContext->rootTableName . ' SET '
            ;

        $this->_processArborescence();

        $this->_sSql .= implode(' = ?, ', (array) $this->_aColumns) . ' = ?';
		$this->_sSql .= $this->_where();
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);
    }
}
