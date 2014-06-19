<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;

class InsertBuilder extends Builder
{
    public $availableOptions = array(
        'fields',
        'values',
    );

    protected function _buildFields(array $fields)
    {
        // $fields can be either attributes or columns. It depends on $useModel 
        // value.
        if ($this->_useModel)
        {
            $fields = $this->_table->columnRealName($fields);
        }

        $this->_query->columns = $fields;
    }

    protected function _buildValues(array $values)
    {
        $this->_query->values = $values;
    }
}
