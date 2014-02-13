<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Option;

class SimpleCondition extends \SimpleAR\Query\Condition
{
    public function toSql($useAliases = true, $toColumn = true)
    {
        $depth      = (string) $this->depth ?: '';
        $tableAlias = ($useAliases ? $this->tableAlias : '') . $depth;

        $res   = array();
        $table = $toColumn ? $this->table : null;
        foreach ($this->attributes as $attribute)
        {
            $res[] = $attribute->toSql($tableAlias, $table);
        }

        return array(
            implode(' ' . self::LOGICAL_OP_AND . ' ', $res), // SQL
            $this->flattenValues(), // Values to bind (flattened).
        );
    }
}