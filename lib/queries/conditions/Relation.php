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

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
class RelationCondition extends \SimpleAR\Query\Condition
{
    /**
     * A Relationship object used when condition is made through a Model relation.
     *
     * Optional
     *
     * @var Relationship
     */
    public $relation;

    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        if (($r = $this->relation) === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        if ($r instanceof BelongsTo)
        {
            // We check that condition makes sense.
            if ($this->logic === 'and' && isset($this->value[1]))
            {
                throw new Exception('Condition does not make sense: ' . strtoupper($o->operator) . ' operator with multiple values for a ' . __CLASS__ . ' relationship.');
            }

            // Get attribute column name.
            if ($this->attribute === 'id')
            {
                $mColumn = $r->cm->column;
                $oTable  = $r->cm->t;
            }
            else
            {
                $mColumn = $r->lm->t->columnRealName($this->attribute);
                $oTable  = $r->lm->t;
            }

            $sLHS = self::leftHandSide($mColumn, $oTable->alias . ($this->depth ?: ''));
            $sRHS = self::rightHandSide($this->value);

            return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
        }

        elseif ($r instanceof HasOne)
        {
            // We check that condition makes sense.
            if ($this->logic === 'and' && isset($this->value[1]))
            {
                throw new Exception('Condition does not make sense: "' . strtoupper($this->operator) . '" operator with multiple values for a ' . __CLASS__ . ' relationship.');
            }

            $mColumn = $r->lm->t->columnRealName($this->attribute);
            $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . ($this->depth ?: ''));
            $sRHS = self::rightHandSide($this->value);

            return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
        }

        elseif ($r instanceof HasMany)
        {
            $oLM = $r->lm;
            $oCM = $r->cm;

            $iPreviousDepth = $this->depth <= 1 ? '' : $this->depth - 1;
            $iDepth = $this->depth ?: '';

            if ($this->logic === 'or')
            {
                $mColumn = $oLM->t->columnRealName($this->attribute);
                $sLHS = self::leftHandSide($mColumn, $oLM->t->alias . '_sub');
                $sRHS = self::rightHandSide($this->value);

                $sLHS_LMColumn = self::leftHandSide($oLM->column, $oLM->t->alias . '_sub');
                $sLHS_CMColumn = self::leftHandSide($oCM->column, $oCM->t->alias . $iPreviousDepth);

                return "EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}_sub
                            WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                            AND   {$sLHS} {$this->operator} {$sRHS}
                        )";
            }
            else // logic == 'and'
            {
                $mColumn = $oLM->t->columnRealName($this->attribute);
                $sLHS2 = self::leftHandSide($mColumn, $oLM->t->alias . '_sub');
                $sLHS3 = self::leftHandSide($mColumn, $oLM->t->alias . '_sub2');
                $sRHS  = self::rightHandSide($this->value);

                $sLHS_LMColumn = self::leftHandSide($oLM->column, $oLM->t->alias . '_sub2');
                $sLHS_CMColumn = self::leftHandSide($oCM->column, $oCM->t->alias . $iPreviousDepth);

                return "NOT EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}_sub
                            WHERE {$sLHS2} {$this->operator} {$sRHS}
                            AND {$sLHS2} NOT IN (
                                SELECT {$sLHS3}
                                FROM $oLM->table {$oLM->alias}_sub2
                                WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                                AND   {$sLHS3}         = {$sLHS2}
                            )
                        )";
            }
        }

        else // ManyMany
        {
            $mColumn = $r->lm->t->columnRealName($this->attribute);

            $iPreviousDepth = $this->depth <= 1 ? '' : $this->depth - 1;
            $iDepth = $this->depth ?: '';

            if ($this->logic === 'or')
            {
                $sRHS = self::rightHandSide($this->value);
                if ($this->attribute === 'id')
                {
                    $sLHS = self::leftHandSide($r->jm->to, $r->jm->alias . $iDepth);
                }
                else
                {
                    $mColumn = $r->lm->t->columnRealName($this->attribute);
                    $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . $iDepth);
                }

                return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
            }
            else // $this->logic === 'and'
            {
                $mColumn = $r->lm->t->columnRealName($this->attribute);
                $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . $iDepth);
                $sRHS = self::rightHandSide($this->value);

                $sCond_JMFrom  = self::leftHandSide($r->jm->from,   $this->jm->alias . $iDepth);
                $sCond_JMFrom2 = self::leftHandSide($r->jm->from,   $this->jm->alias . '_sub');
                $sCond_JMTo2   = self::leftHandSide($r->jm->to,     $this->jm->alias . '_sub');
                $sCond_LM2     = self::leftHandSide($r->lm->column, $this->lm->alias . '_sub');

                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$r->lm->table} {$this->lm->alias}_sub
                            WHERE {$sLHS} {$this->operator} {$sRHS}
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$r->jm->table} {$this->jm->alias}_sub
                                WHERE {$sCond_JMFrom} = {$sCond_JMFrom2}
                                AND   {$sCond_JMTo2}  = {$sCond_LM2}
                            )
                        )";

            }
        }
    }
}
