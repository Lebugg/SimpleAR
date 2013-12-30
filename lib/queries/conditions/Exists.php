<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Condition;

class ExistsCondition extends Condition
{
    /**
     * A Relationship object used when condition is made through a Model relation.
     *
     * Optional
     *
     * @var Relationship
     */
    public $relation;

    /**
     * EXISTS or NOT EXISTS? That is what this variable is aimed for.
     *
     * @var bool
     */
    public $exists;

    /**
     * This function is used to format value array for PDO.
     *
     * @return array
     *      Condition values.
     */
    public function flattenValues()
    {
        $aRes = array();

        foreach ((array) $this->subconditions as $mItem)
        {
            if ($mItem instanceof Condition)
            {
                $aRes = array_merge($aRes, $mItem->flattenValues());
            }
        }

        return $aRes;
    }

    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        if (($r = $this->relation) === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        $sAlias    = $r->lm->alias . ($this->depth ?: '');
        $sCMColumn = self::leftHandSide($r->cm->column, $r->cm->alias . ($this->depth - 1 ?: ''));

        // Not the same for ManyMany, we use join table.
        if ($r instanceof ManyMany)
        {
            $sTable    = $r->jm->table;
            $sLMColumn = self::leftHandSide($r->jm->from, $sAlias);
        }
        // For all other Relation type, we use linked table.
        else
        {
            $sTable    = $r->lm->table;
            $sLMColumn = self::leftHandSide($r->lm->column, $sAlias);
        }

        // True for EXISTS, false for NOT EXISTS.
        $b = $this->exists ? '' : 'NOT';

        // Easy subquery.
        // $b contains '' or 'NOT'. It relies on $this->exists value.
        return " $b EXISTS (
                    SELECT NULL
                    FROM {$sTable} {$sAlias}
                    WHERE {$sLMColumn} = {$sCMColumn}
                )";
    }
}

