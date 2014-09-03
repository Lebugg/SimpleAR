<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;

class InsertBuilder extends Builder
{
    public $type = Builder::INSERT;

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
            $fields = $this->convertAttributesToColumns($fields, $this->_table);
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

    protected function _onAfterBuild()
    {
        $this->_components['into'] = $this->_useModel
            ? $this->_table->name
            : $this->_table;
    }
}
