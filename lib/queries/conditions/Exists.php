<?php
namespace SimpleAR\Query\Condition;

class ExistsCondition extends \SimpleAR\Query\Condition
{
    /**
     * A Relationship object used when condition is made through a Model relation.
     *
     * Optional
     *
     * @var Relationship
     */
    public $relation;

    public $exists;

    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        $r = $this->relation;

        if ($r === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        // True for EXISTS, false for NOT EXISTS.
        $b = $this->exists ? '' : 'NOT';

        $sCMColumn = self::leftHandSide($r->cm->column, $r->cm->alias . ($this->depth - 1 ?: ''));

        // Not the same for ManyMany, we check join table.
        if ($r instanceof ManyMany)
        {
            $sTable    = $r->jm->table;
            $sLMColumn = self::leftHandSide($r->jm->from, 'a');
        }
        // For all other Relation type, we check linked table.
        else
        {
            $sTable    = $r->lm->table;
            $sLMColumn = self::leftHandSide($r->lm->column, 'a');
        }

        // Easy subquery.
        // $b contains '' or 'NOT'. It relies on $this->exists value.
        return " $b EXISTS (
                    SELECT NULL
                    FROM {$sTable} a
                    WHERE {$sLMColumn} = {$sCMColumn}
                )";
    }
}

