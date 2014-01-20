<?php
namespace SimpleAR\Query\Condition;

use \SimpleAR\Query\Option;

class SimpleCondition extends \SimpleAR\Query\Condition
{
    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        $sAlias   = $bUseAliases ? $this->table->alias : '';
        $mColumns = $bToColumn   ? $this->table->columnRealName($this->attribute) : $this->attribute;

        $sLHS = self::leftHandSide($mColumns, $sAlias);
        $sRHS = self::rightHandSide($this->value);

        return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
    }
}
