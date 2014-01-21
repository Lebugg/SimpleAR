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
    protected static $_options = array('conditions', 'has');

    public function _compile()
    {
        $sJoin = $this->_processArborescence();

		$this->_sSql  = 'SELECT COUNT(*)';
		$this->_sSql .= ' FROM ' . $this->_context->rootTableName . ' ' .  $this->_context->rootTable->alias .  ' ' . $sJoin;
		$this->_sSql .= $this->_where();
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
