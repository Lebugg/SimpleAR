<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Builder\WhereBuilder;

class DeleteBuilder extends WhereBuilder
{
    public $type = Builder::DELETE;

    public $availableOptions = array(
        'root',
        'conditions',
    );

    public function root($root)
    {
        parent::root($root);

        if (! $this->_useModel)
        {
            $this->_components['deleteFrom'] = $root; return;
        }

        // We *have to* set the alias and not the table name. Since this is the 
        // root that we are setting, we know its place in the arborescence. 
        // Thus, we just have to generate its alias out of the table name.
        $tableAlias = $this->_getAliasForTableName($this->_table->name);
        $this->_components['deleteFrom'] = $tableAlias;
    }
}
