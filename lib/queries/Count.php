<?php
/**
 * This file contains the Update class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles SELECT COUNT(*) queries.
 */
class Count extends Select
{
    protected static $_aOptions = array('conditions', 'has');

    public function _compile()
    {
        $this->_processArborescence();
        $this->_where();

		$this->_sSql  = 'SELECT COUNT(*)';
		$this->_sSql .= ' FROM ' . $this->_oRootTable->name . ' ' .  $this->_oRootTable->alias .  ' ' . $this->_sJoin;
		$this->_sSql .= $this->_sWhere;
    }

    /**
     * Count result getter.
     *
     * @see http://www.php.net/manual/en/pdostatement.fetch.php
     *
     * @return int The count result.
     */
    public function res()
    {
        return $this->_oSth->fetch(\PDO::FETCH_COLUMN);
    }
}
