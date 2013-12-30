<?php
namespace SimpleAR\Query\Condition;

class SimpleCondition extends \SimpleAR\Query\Condition
{
    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        $mColumns = $bToColumn   ? $this->table->columnRealName($this->attribute) : $this->attribute;
        $sAlias   = $bUseAliases ? $this->table->alias : '';

        $sLHS = self::leftHandSide($mColumns, $sAlias);
        $sRHS = self::rightHandSide($this->value);

        return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
    }
}
