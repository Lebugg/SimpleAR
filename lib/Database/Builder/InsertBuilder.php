<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;

class InsertBuilder extends Builder
{
    public $type = Builder::INSERT;

    public $availableOptions = array(
        'root',
        'fields',
        'values',
    );

    public function useRootModel($class)
    {
        parent::useRootModel($class);
        $this->_components['into'] = $this->_table->name;
    }

    public function useRootTableName($table)
    {
        parent::useRootTableName($table);
        $this->_components['into'] = $table;
    }

    protected function _buildFields(array $fields)
    {
        // $fields can be either attributes or columns. It depends on $useModel 
        // value.
        if ($this->_useModel)
        {
            $fields = $this->_table->columnRealName($fields);
        }

        $this->_components['insertColumns'] = $fields;
    }

    protected function _buildValues(array $values)
    {
        $this->_components['values'] = $values;
        $this->addValueToQuery($values, 'values');
    }
}