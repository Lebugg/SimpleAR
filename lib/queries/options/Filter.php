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
     * Value:
     * ------
     * The value can be a filter name or an attribute array.
     *
     * @return array Attribute to fetch.
     *
     * @see Model::columnsToSelect()
     * @see Query::attributeAliasing()
     * @see Query\Select::$_selects
     */
    public function build()
    {
        // It is an array of attributes.
        if (is_array($this->_value))
        {
            $columns = $this->_value;

            // Let's assure that primary key is in the columns to select.
            // (We cannot do that without a model class.)
            if ($this->_context->useModel)
            {
                $rootTable = $this->_context->rootTable;

                $columns = array_merge($columns, (array) $rootTable->primaryKey);
                // There might be duplicates.
                $columns = array_unique($columns);
            }
        }

        // It is a filter.
        else
        {
            // We need to use models.
            if (! $this->_context->useModel)
            {
                throw new MalformedOptionException('"filter" option cannot be used without using model.');
            }

            $rootModel = $this->_context->rootModel;
            $columns   = $rootModel::columnsToSelect($this->_value);
        }

        // Shortcuts.
		$rootAlias   = $this->_context->useAlias       ? $this->_context->rootTableAlias  : '';
        $resultAlias = $this->_context->useResultAlias ? $this->_context->rootResultAlias : '';

        $columns = Query::columnAliasing($columns, $rootAlias, $resultAlias);

        return $columns;
    }
}
