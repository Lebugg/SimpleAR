<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query;

class Filter extends Option
{
    public function build()
    {
        // Mandatory for syntax respect.
		$sRootModel   = $this->_context->rootModel;
        // Shortcuts.
		$sRootAlias   = $this->_context->rootTable->alias;
        $sResultAlias = $this->_context->rootResultAlias;

        $aColumns = $sRootModel::columnsToSelect($this->_value);
        $aColumns = Query::columnAliasing($aColumns, $sRootAlias, $sResultAlias);

        call_user_func($this->_callback, $aColumns);
    }
}
