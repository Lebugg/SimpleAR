<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query;

class Filter extends Option
{
    /**
     * Handle "filter" option.
     *
     * First, it retrieve an attribute array from the Model.
     * Then, it apply aliasing according to contextual values.
     *
     * Final columns to select (in a string form) are stored in
     * Select::$_selects.
     *
     * @param string $filter The filter to apply or null to not filter.
     *
     * @return void
     *
     * @see Model::columnsToSelect()
     * @see Query::attributeAliasing()
     * @see Query\Select::$_selects
     */
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

        return $columns;
    }
}
