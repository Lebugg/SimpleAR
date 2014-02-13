<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Condition;
use SimpleAR\Exception;

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

    public $subconditions;

    /**
     * @override.
     */
    public function canMergeWith(Condition $c)
    {
        return parent::canMergeWith($c)
                && $this->exists === $c->exists;
    }
    public function toSql($useAliases = true, $toColumn = true)
    {
        if (($r = $this->relation) === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        $depth         = (string) ($this->depth ?: '');
        $previousDepth = (string) ($this->depth - 1 ?: '');

        $cmColumn = self::leftHandSide($r->cm->column, $r->cm->alias .  $previousDepth);

        // Not the same for ManyMany, we use join table.
        if ($r instanceof ManyMany)
        {
            // If there are subconditions, we use linked table.
            if ($this->attributes)
            {
                $tableAlias = $r->lm->alias . $depth;
                $tableName  = $r->lm->table;
                $lmColumn   = self::leftHandSide($r->lm->column, $tableAlias);
            }
            // Otherwise, the middle table suffises.
            else
            {
                $tableAlias = $r->jm->alias . $depth;
                $tableName  = $r->jm->table;
                $lmColumn   = self::leftHandSide($r->jm->from, $tableAlias);
            }
        }
        // For all other Relation type, we use linked table.
        else
        {
            $tableAlias = $r->lm->alias . $depth;
            $tableName  = $r->lm->table;
            $lmColumn   = self::leftHandSide($r->lm->column, $tableAlias);
        }

        // True for EXISTS, false for NOT EXISTS.
        $b = $this->exists ? '' : 'NOT';

        $subconditions = array();
        foreach ($this->attributes as $attribute)
        {
            $subconditions[] = $attribute->toSql($r->lm->t, $tableAlias);
        }

        $subconditionValues = array();
        if ($this->subconditions)
        {
            $tmp = $this->subconditions->toSql();

            $subconditions[]    = $tmp[0];
            $subconditionValues = $tmp[1];
        }

        $subconditions = implode(' AND ', $subconditions);
        if ($subconditions)
        {
            $subconditions = ' AND ' . $subconditions;
        }

        // Easy subquery.
        // $b contains '' or 'NOT'. It relies on $this->exists value.
        $sql = " $b EXISTS (
                    SELECT NULL
                    FROM `{$tableName}` `{$tableAlias}`
                    WHERE {$lmColumn} = {$cmColumn}
                    {$subconditions}
                )";

        return array(
            $sql,
            array_merge($this->flattenValues(), $subconditionValues),
        );
    }
}

