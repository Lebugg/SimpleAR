<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query;
use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

use \SimpleAR\MalformedOptionException;

class With extends Option
{
    public function build()
    {
        if (! $this->_context->useModel)
        {
            throw new MalformedOptionException('Cannot use "with" option when not using models.');
        }
        
        $res = array();
        foreach((array) $this->_value as $relation)
        {
            $node     = $this->_arborescence->add(explode('/', $relation), Arborescence::JOIN_LEFT, true);
            $relation = $node->relation;

            $lmClass = $relation->lm->class;
            $columns = $lmClass::columnsToSelect($relation->filter);

            $tableAlias = $this->_context->useAlias
                ? $relation->lm->alias . ($node->depth ?: '')
                : ''
                ;

            $columns = Query::columnAliasing($columns, $tableAlias, $relation->name);

            $res = array_merge($res, $columns);
        }

        call_user_func($this->_callback, $res);
    }
}
