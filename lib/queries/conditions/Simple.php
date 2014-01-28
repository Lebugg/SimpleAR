<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Option;

class SimpleCondition extends \SimpleAR\Query\Condition
{
    public function toSql($useAliases = true, $toColumn = true)
    {
        $depth      = (string) $this->depth ?: '';
        $tableAlias = ($useAliases ? $this->table->alias : '') . $depth;

        $res = array();
        foreach ($this->attributes as $attribute)
        {
            $columns = $toColumn ? $this->table->columnRealName($attribute->name) : $attribute->name;

            $lhs = self::leftHandSide($columns, $tableAlias);
            $rhs = self::rightHandSide($attribute->value);

            $res[] = $lhs . ' ' . $attribute->operator . ' ' . $rhs;
        }

        return array(
            implode(' ' . self::LOGICAL_OP_AND . ' ', $res), // SQL
            $this->flattenValues(), // Values to bind (flattened).
        );
    }
}
