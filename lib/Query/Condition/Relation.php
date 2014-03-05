<?php
/**
 * This file contains the Condition class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query\Condition;

use SimpleAR\BelongsTo;
use SimpleAR\HasOne;
use SimpleAR\HasMany;
use SimpleAR\ManyMany;

use SimpleAR\Exception;

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
class RelationCondition extends \SimpleAR\Query\Condition
{
    /**
     * A Relation object used when condition is made through a Model relation.
     *
     * Optional
     *
     * @var Relation
     */
    public $relation;

    public function toSql($useAliases = true, $toColumn = true)
    {
        if (($r = $this->relation) === null)
        {
            throw new Exception('Cannot transform RelationCondition to SQL because “relation” is not set.');
        }

        $res = array();

        switch (get_class($r))
        {
            case 'SimpleAR\Relation\BelongsTo':
                foreach ($this->attributes as $attribute)
                {
                    // We check that condition makes sense.
                    if ($attribute->logic === 'and' && isset($attribute->value[1]))
                    {
                        throw new Exception('Condition does not make sense: ' . strtoupper($attribute->operator) . ' operator with multiple values for a ' . __CLASS__ . ' relationship.');
                    }

                    // Get attribute column name.
                    if ($attribute->name === 'id')
                    {
                        $column = $r->cm->column;
                        $table  = $r->cm->t;
                    }
                    else
                    {
                        $column = $r->lm->t->columnRealName($attribute->name);
                        $table  = $r->lm->t;
                    }

                    $lhs = self::leftHandSide($column, $table->alias . ($this->depth ?: ''));
                    $rhs = self::rightHandSide($attribute->value);

                    $res[] = $lhs . ' ' . $attribute->operator . ' ' . $rhs;
                }
                break;

        case 'SimpleAR\Relation\HasOne':
            foreach ($this->attributes as $attribute)
            {
                // We check that condition makes sense.
                if ($attribute->logic === 'and' && isset($attribute->value[1]))
                {
                    throw new Exception('Condition does not make sense: "' . strtoupper($attribute->operator) . '" operator with multiple values for a ' . __CLASS__ . ' relationship.');
                }

                $column = $r->lm->t->columnRealName($attribute->name);
                $lhs = self::leftHandSide($column, $r->lm->t->alias . ($this->depth ?: ''));
                $rhs = self::rightHandSide($attribute->value);

                $res[] = $lhs . ' ' . $attribute->operator . ' ' . $rhs;
            }
            break;

        case 'SimpleAR\Relation\HasMany':
            $depth         = (string) ($this->depth ?: '');
            $previousDepth = (string) ($this->depth - 1 ?: '');

            $orConditions  = array();
            $andConditions = array();

            foreach ($this->attributes as $attribute)
            {
                if ($attribute->logic === 'or')
                {
                    $orConditions[] = $attribute->toSql($r->lm->t->alias . '_sub', $r->lm->t);
                }
                else // logic == 'and'
                {
                    $column = $r->lm->t->columnRealName($attribute->name);

                    $lhs2 = self::leftHandSide($column, $r->lm->t->alias . '_sub');
                    $lhs3 = self::leftHandSide($column, $r->lm->t->alias . '_sub2');
                    $rhs  = self::rightHandSide($attribute->value);

                    $andConditions[] = array(
                        'lhs2'     => $lhs2,
                        'lhs3'     => $lhs3,
                        'rhs'      => $rhs,
                        'operator' => $this->operator,
                    );
                }
            }

            // Combine conditions.
            if ($orConditions)
            {
                $lhs_LMColumn = self::leftHandSide($r->lm->column, $r->lm->t->alias . '_sub');
                $lhs_CMColumn = self::leftHandSide($r->cm->column, $r->cm->t->alias . $previousDepth);

                $condition = implode(' AND ', $orConditions);

                $res[] = "EXISTS (
                            SELECT NULL
                            FROM `{$r->lm->table}` `{$r->lm->alias}_sub`
                            WHERE {$lhs_LMColumn} = {$lhs_CMColumn}
                            AND   {$condition}
                        )";
            }
            if ($andConditions)
            {
                $lhs_LMColumn = self::leftHandSide($r->lm->column, $r->lm->t->alias . '_sub2');
                $lhs_CMColumn = self::leftHandSide($r->cm->column, $r->cm->t->alias . $previousDepth);

                $firstWhere= array();
                $subConditions        = array();
                foreach ($andConditions as $condition)
                {
                    extract($condition);

                    $firstWhereConditions[] = $lhs2 . ' ' . $operator . ' ' .  $rhs;
                    
                    $subConditions[] = "{$lhs2} NOT IN (
                                            SELECT {$lhs3}
                                            FROM `{$r->lm->table}` `{$r->lm->alias}_sub2`
                                            WHERE {$lhs_LMColumn} = {$lhs_CMColumn}
                                            AND   {$lhs3}         = {$lhs2}
                                        )";
                }

                $firstWhereConditions = implode(' AND ', $firstWhereConditions);
                $subConditions        = implode(' AND ', $subConditions);

                $res[] = "NOT EXISTS (
                            SELECT NULL
                            FROM `{$r->lm->table}` `{$r->lm->alias}_sub`
                            WHERE {$firstWhereConditions}
                            AND {$subConditions}
                        )";
            }
            break;

        case 'SimpleAR\Relation\ManyMany':
            $depth         = (string) ($this->depth ?: '');
            $previousDepth = (string) ($this->depth - 1 ?: '');

            $andConditions = array();

            foreach ($this->attributes as $attribute)
            {
                $column = $r->lm->t->columnRealName($attribute->name);

                if ($attribute->logic === 'or')
                {
                    $rhs = self::rightHandSide($attribute->value);
                    if ($attribute->name === 'id')
                    {
                        $lhs = self::leftHandSide($r->jm->to, $r->jm->alias . $depth);
                    }
                    else
                    {
                        $lhs = self::leftHandSide($column, $r->lm->t->alias . $depth);
                    }

                    $res[] = $lhs . ' ' . $attribute->operator . ' ' . $rhs;
                }
                else // $attribute->logic === 'and'
                {
                    $lhs = self::leftHandSide($column, $r->lm->t->alias . $depth);
                    $rhs = self::rightHandSide($attribute->value);

                    $andConditions[] = $lhs . ' ' . $attribute->operator . ' ' . $rhs;
                }

            } // end foreach of ManyMany case.

            if ($andConditions)
            {
                $cond_JMFrom  = self::leftHandSide($r->jm->from,   $r->jm->alias . $depth);
                $cond_JMFrom2 = self::leftHandSide($r->jm->from,   $r->jm->alias . '_sub');
                $cond_JMTo2   = self::leftHandSide($r->jm->to,     $r->jm->alias . '_sub');
                $cond_LM2     = self::leftHandSide($r->lm->column, $r->lm->alias . '_sub');

                $whereCondition = implode(' AND ', $andConditions);

                $res[] = "NOT EXISTS (
                            SELECT NULL
                            FROM `{$r->lm->table}` `{$attribute->lm->alias}_sub`
                            WHERE {$whereCondition}
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM `{$r->jm->table}` `{$attribute->jm->alias}_sub`
                                WHERE {$cond_JMFrom} = {$cond_JMFrom2}
                                AND   {$cond_JMTo2}  = {$cond_LM2}
                            )
                        )";

            }
            break;

            default:
                throw new Exception('Unknown relation type: "' . get_class($r) .  '".');

        } // end switch.

        return array(
            implode(' AND ', $res),
            $this->flattenValues(),
        );
    }
}

// HasMany - AND
/*
return "NOT EXISTS (
            SELECT NULL
            FROM $oLM->table {$oLM->alias}2
            WHERE {$oLM->alias}2.$column $o->operator $sValue
            AND {$oLM->alias}2.$column NOT IN (
                SELECT {$oLM->alias}3.$column
                FROM $oLM->table {$oLM->alias}3
                WHERE {$oLM->alias}3.$oLM->column = {$oCM->alias}.{$oCM->column}
                AND   {$oLM->alias}3.$column     = {$oLM->alias}2.$column
            )
        )";
*/
/*
return "NOT EXISTS (
            SELECT NULL
            FROM {$oLM->table} {$oLM->alias}2
            WHERE {$condition}
            AND NOT EXISTS (
                SELECT NULL
                FROM {$oLM->table} {$oLM->alias}3
                AND   {$sOtherCondition}
                WHERE {$oLM->alias}3.{$oLM->column} = {$oCM->alias}.{$oCM->column}
            )
        )";
*/

// ManyMany - AND
/*
return "NOT EXISTS (
            SELECT NULL
            FROM {$this->lm->table} {$this->lm->alias}2
            WHERE {$this->lm->alias}2.{$column} $o->operator $sRightHandSide
            AND NOT EXISTS (
                SELECT NULL
                FROM {$this->jm->table} {$this->jm->alias}2
                WHERE {$this->jm->alias}.{$this->jm->from} = {$this->jm->alias}2.{$this->jm->from}
                AND   {$this->jm->alias}2.{$this->jm->to}  = {$this->lm->alias}2.{$this->lm->column}
            )
        )";
*/
