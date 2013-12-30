<?php
namespace SimpleAR\Query;

class Exists extends Select
{
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
		}

        if (isset($aOptions['has']))
        {
            $this->_has((array) $aOptions['has']);
        }
	}

    protected function _compile()
    {
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

        $this->_processArborescence();
        $this->_where();

		$this->sql  = 'SELECT NULL';
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
    }
}
