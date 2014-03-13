<?php namespace SimpleAR\Query\Condition;

use \SimpleAR\Query\Condition;
use SimpleAR\Query\Arborescence;
use \SimpleAR\Exception;

class Relation extends Condition
{
    public $subconditions;

    public function compile(Arborescence $root, $useAlias = false, $toColumn = false)
    {
        $sql = array();
        $val = array();

        $nCurrent = $root->find($this->relations);
        $relation = $nCurrent->relation;

        switch (get_class($r))
        {
            case 'SimpleAR\Relation\BelongsTo':
                foreach ($this->subconditions as $c)
                {
                    // Get attribute column name.
                    if ($c->attribute === 'id')
                    {
                        $tmp = $c->compile($cm, $cmTableAlias);
                    }
                    else
                    {
                        $tmp = $c->compile($r->lm->class, $lmTableAlias);
                    }

                    $sql[] = $tmp[0];
                    $val   = array_merge($val, $tmp[1]);
                }
                break;

        case 'SimpleAR\Relation\HasOne':
            foreach ($this->subconditions as $c)
            {
                $tmp   = $c->compile($r->lm->class, $lmTableAlias);

                $sql[] = $tmp[0];
                $val   = array_merge($val, $tmp[1]);
            }
            break;

        case 'SimpleAR\Relation\HasMany':
            $orSql = array();
            $orVal = array();

            $andConditions = null;

            foreach ($this->subconditions as $c)
            {
                if ($c->logic === 'or')
                {
                    $tmp = $attribute->compile($r->lm->class, $r->lm->t->alias . '_sub');

                    $orSql[] = $tmp[0];
                    $orVal   = array_merge($orVal, $tmp[0]);
                }
                else // logic == 'and'
                {
                    throw new Exception('Not implemented.');

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
            if ($orSql)
            {
                $lhs_LMColumn = $this->_attributeToSql($r->lm->column, $lmTableAlias . '_sub');
                $lhs_CMColumn = $this->_attributeToSql($r->cm->column, $cmTableAlias);

                $condition = implode(' AND ', $orSql);

                $sql[] = "EXISTS (
                            SELECT NULL
                            FROM `{$r->lm->table}` `{$lmTableAlias}_sub`
                            WHERE {$lhs_LMColumn} = {$lhs_CMColumn}
                            AND   {$condition}
                        )";

                $val = array_merge($val, $orVal);
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
            $andConditions = array();

            foreach ($this->subconditions as $c)
            {
                if ($c->logic === 'or')
                {
                    if ($c->attribute == 'id')
                    {
                        $lhs = $c->_attributeToSql($r->jm->to, $cmTableAlias .  '_m');
                        $op  = $c->_operatorToSql();
                        $rhs = $c->_valueToSql();

                        $sql[] = $lhs . ' ' . $op . ' ' . $rhs;
                        $val   = array_merge($val, $c->flattenValues());
                    }
                    else
                    {
                        $tmp = $c->compile($lmTableAlias, $relation->lm->class);

                        $sql[] = $tmp[0];
                        $val   = array_merge($val, $tmp[1]);
                    }
                }

                else // $attribute->logic === 'and'
                {
                    throw new Exception('Not implemented.');

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
            implode(' AND ', $sql),
            $val,
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
