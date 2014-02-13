<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query;
use \SimpleAR\Query\Option;

class Fields extends Option
{
    public function build()
    {
        $fields = (array) $this->_value;

        // We have to translate attribute to columns.
        if ($this->_context->useModel)
        {
            // We cast into array because columnRealName can return a string
            // even if we gave it an array.
            $fields = (array) $this->_context->rootTable->columnRealName($fields);
        }

        // Use table alias?
        $tableAlias = $this->_context->useAlias
            ? $this->_context->rootTableAlias
            : '';

        $fields = Query::columnAliasing($fields, $tableAlias);

        return $fields;
    }
}
