<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query;

class Filter extends Option
{
    public function build()
    {
        if (!$this->_context->useModel)
        {
            throw new MalformedOptionException('"filter" option cannot be used without using model.');
        }

        // Mandatory for syntax respect.
		$rootModel   = $this->_context->rootModel;

        // Shortcuts.
		$rootAlias   = $this->_context->useAlias       ? $this->_context->rootTableAlias  : '';
        $resultAlias = $this->_context->useResultAlias ? $this->_context->rootResultAlias : '';

        $columns = $rootModel::columnsToSelect($this->_value);
        $columns = Query::columnAliasing($columns, $rootAlias, $resultAlias);

        call_user_func($this->_callback, $columns);
    }
}
