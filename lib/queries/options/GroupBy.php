<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

/**
 * Supports:
 * - useModel
 * - useAlias
 *
 */
class GroupBy extends Option
{
    public function build()
    {
        $res = array();

        foreach ((array) $this->_value as $attribute)
        {
            // $attribute is now an object.
            $attribute = self::_parseAttribute($attribute);

            $columns = $attribute->attribute;
            if ($this->_context->useModel)
            {
                // Add related model(s) in join arborescence.
                $node    = $this->_arborescence->add($attribute->relations, Arborescence::JOIN_INNER, true);
                $columns = (array) $node->table->columnRealName($columns);
            }

            $tableAlias = '';
            if ($this->_context->useAlias)
            {
                $tableAlias = $node
                    ? $node->table->alias . ($node->depth ?: '') 
                    : $this->_context->rootTableAlias
                    ;

               $tableAlias = '`' . $tableAlias . '`.';
            }

            foreach ($columns as $column)
            {
                $res[] = $tableAlias . '`' . $column . '`';
            }
        }

        return $res;
    }
}
