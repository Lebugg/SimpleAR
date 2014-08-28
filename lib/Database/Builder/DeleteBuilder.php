<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Builder\WhereBuilder;

class DeleteBuilder extends WhereBuilder
{
    public $type = Builder::DELETE;

    public function root($root)
    {
        parent::root($root);

        if (! $this->_useModel)
        {
            $this->_components['deleteFrom'] = $root; return $this;
        }

        // Using several tables is not yet possible with delete builder. Thus, 
        // we set the table name in "deleteFrom" component. We won't bother with 
        // any table alias.
        $this->_components['deleteFrom'] = $this->_table->name;

        return $this;
    }

    protected function _onAfterBuild()
    {
        // Never used yet. But still, it is possible.
        if (is_array($this->_components['deleteFrom']))
        {
            $this->_components['using'] = array_values($this->_joinClauses);
        }
    }
}
