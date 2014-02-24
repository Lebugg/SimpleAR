<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query;
use \SimpleAR\Query\Option;

use \SimpleAR\Database\Expression;

use \SimpleAR\Exception\MalformedOption;

class Filter extends Option
{
    public $columns;

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

        // It is a filter or there is no filter (all columns are to be
        // selected).
        elseif ($this->_value === null || is_string($this->_value))
        {
            // We need to use models.
            if (! $this->_context->useModel)
            {
                throw new MalformedOption('"filter" option cannot be used without using model.');
            }

            $rootModel = $this->_context->rootModel;
            $columns   = $rootModel::columnsToSelect($this->_value);
        }

        // It is an raw expression.
        //
        // Two syntaxes accepted for a raw expression:
        //
        //  * An array of column;
        //  * A comma-separated list of column.
        //
        elseif ($this->_value instanceof Expression)
        {
            $value = $this->value->val();
            
            $columns = is_string($value)
                ? array_map(function($el) {
                        return trim($el);
                    }, explode(',', $value))
                : $value
                ;
        }

        else
        {
            throw new MalformedOption('Bad value for filter option: ' .  var_export($this->_value, true). '.');
        }

        // Shortcuts.
		$rootAlias   = $this->_context->useAlias       ? $this->_context->rootTableAlias  : '';
        $resultAlias = $this->_context->useResultAlias ? $this->_context->rootResultAlias : '';

        $this->columns = Query::columnAliasing($columns, $rootAlias, $resultAlias);
    }
}
