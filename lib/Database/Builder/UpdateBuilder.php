<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Builder\WhereBuilder;
use \SimpleAR\Database\JoinClause;

class UpdateBuilder extends WhereBuilder
{
    public $type = Builder::UPDATE;

    public $availableOptions = array(
        'root',
        'set',
        'conditions',
    );

    public function root($root)
    {
        parent::root($root);

        if (! $this->_useModel)
        {
            $this->_components['updateFrom'] = $root; return;
        }

        $joinClause = new JoinClause($this->_table->name,  '');
        $this->_components['updateFrom'] = array($joinClause);

        return $this;
    }

    /**
     * Add a set clause.
     *
     * Usage:
     * ------
     *
     * There are two ways to use this method:
     *
     *  1) `$builder->set('myAttribute', 'myValue');`
     *  2) `$builder->set(['myAttr' => 'myValue', 'myAttr2' => 'myValue2']);`
     *
     * @param string|array $attribute The attribute name or an array of 
     * attribute/value pairs.
     * @param mixed  $value
     *
     * @return $this
     */
    public function set($attribute, $value = null)
    {
        if (is_array($attribute))
        {
            foreach ($attribute as $attr => $val)
            {
                $this->_options['set'][$attr] = $val;
            }
        }
        else
        {
            $this->_options['set'][$attribute] = $value;
        }

        return $this;
    }

    protected function _buildSet(array $sets)
    {
        foreach ($sets as $attribute => $value)
        {
            list($tableAlias, $columns) = $this->_processExtendedAttribute($attribute);

            // Set does not allow multiple attributes form.
            $column = $columns[0];

            $this->_components['set'][] = compact('tableAlias', 'column', 'value');

            // One other task to do: move value over one place.
            $this->addValueToQuery($value, 'set');
        }
    }
}
