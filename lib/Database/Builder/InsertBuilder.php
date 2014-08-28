<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;

class InsertBuilder extends Builder
{
    public $type = Builder::INSERT;

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

    /**
     * Set the attributes in which to insert values.
     *
     * Component: "insertColumns"
     * ----------
     *
     * @param array $fields The attributes.
     */
    public function fields(array $fields)
    {
        // $fields can be either attributes or columns. It depends on $useModel 
        // value.
        if ($this->_useModel)
        {
            $fields = $this->_table->columnRealName($fields);
        }

        $this->_components['insertColumns'] = $fields;
    }

    /**
     * Set values to insert.
     *
     * Component: "values"
     * ----------
     *
     * @param array $values The values to insert.
     */
    public function values(array $values)
    {
        $this->_components['values'] = $values;
        $this->addValueToQuery($values, 'values');
    }

}
