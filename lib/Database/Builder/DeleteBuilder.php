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

        // Using several tables is not yet possible with delete builder. Thus, 
        // we set the table name in "deleteFrom" component. We won't bother with 
        // any table alias.
        $this->_components['deleteFrom'] = $this->_table->name;
    }
}
