<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Model;
use \SimpleAR\Query;
use \SimpleAR\Query\Option;

use \SimpleAR\Database\Expression;
use \SimpleAR\Exception\MalformedOption;

class Filter extends Option
{
    protected static $_name = 'filter';

    public $attributes;
    public $toColumn;

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
    public function build($useModel, $model = null)
    {
        // It is an array of attributes.
        if (is_array($this->_value))
        {
            $columns = $this->_value;

            /* if ($useModel) */
            /* { */
            /*     $table = $model::table(); */

            /*     if ($table->isSimplePrimaryKey) */
            /*     { */
            /*         $columns[] = 'id'; */
            /*     } */
            /*     else */
            /*     { */
            /*         $columns = array_unique(array_merge($columns, $table->primaryKey)); */
            /*     } */
            /* } */

            $this->attributes = $columns;
            $this->toColumn   = $useModel;
        }

        // It is a filter or there is no filter (all columns are to be
        // selected).
        elseif ($this->_value === null || is_string($this->_value))
        {
            // We need to use models.
            if (! $useModel)
            {
                throw new MalformedOption('"filter" option cannot be used this
                        way without using models.');
            }

            $this->attributes = $model::columnsToSelect($this->_value);
            $this->toColumn   = false;
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
            $value = $this->_value->val();
            
            $columns = is_string($value)
                ? array_map(function($el) {
                        return trim($el);
                    }, explode(',', $value))
                : $value
                ;

            $this->attributes = $columns;
            $this->toColumn   = false;
        }

        else
        {
            throw new MalformedOption('Bad value for filter option: ' .
                    var_export($this->_value, true). '.');
        }
    }
}
