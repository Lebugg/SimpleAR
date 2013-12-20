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
class Count extends \SimpleAR\Query\Where
{
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
            $this->_conditions($aOptions['conditions']);
		}

        $this->_processArborescence();
        $this->_where();

		$this->sql  = 'SELECT COUNT(*)';
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
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
