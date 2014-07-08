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
    }

    /**
     * Add a set clause.
     *
     * @param string $attribute
     * @param mixed  $value
     */
    public function set($attribute, $value)
    {
        $this->_options['set'][$attribute] = $value;
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
            $this->addValueToQuery($value);
        }
    }
}
