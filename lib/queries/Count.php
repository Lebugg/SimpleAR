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
    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build(array $aOptions)
	{
		if (isset($aOptions['conditions']))
		{
            $this->_conditions($aOptions['conditions']);
		}

		if (isset($aOptions['has']))
		{
            $this->_has($aOptions['has']);
		}
	}

    public function _compile()
    {
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

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
