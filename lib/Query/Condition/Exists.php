<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Exception;
use SimpleAR\Facades\DB;
use SimpleAR\Query\Condition;
use SimpleAR\Query\Arborescence;
use SimpleAR\Relation\ManyMany as ManyMany;

class Exists extends Condition
{
    public function compile(Arborescence $nCurrent, $useAlias = false, $toColumn = false)
    {
        // First we need to construct the join between the current model and the
        // linked model that we need for the EXISTS subquery: subquery has to be
        // attached to the main one in order to make sense.
        //
        // First, get the relation that links CM and LM.
        $nNext    = $nCurrent->find($this->relations);
        $relation = $nNext->relation;

        // Then, CM column.
        $cmColumn = $this->_attributeToSql($relation->cm->column, $useAlias ?  $nCurrent->alias : '');

        // Finally LM column. This is a little bit complex for ManyMany
        // relations: sometimes we just need the middle table, sometimes we also
        // need the LM table. Actually, we need it when there are
        // subconditions to the EXISTS.


        $qLmTable      = DB::quote($relation->lm->table);
        $lmTableAlias = $nNext->alias;
        $qLmTableAlias = DB::quote($lmTableAlias);
        $join         = '';

        if ($relation instanceof ManyMany)
        {
            // Suconditions? If yes, join LM table *and* middle table. If no,
            // only join middle table.

            if ($this->subconditions)
            {
                $join = 'INNER JOIN ' . DB::quote($relation->jm->table) . '_middle ON '
                    . $lmTableAlias . '.' . $relation->lm->column . ' = _middle.' . $relation->jm->to;

                $lmColumn = '`' . $relation->jm->alias . '`.`' . $relation->jm->from . '`';
            }
            else
            {
                $lmTableAlias = '_middle';
                $lmTable      = $relation->jm->table;
                $lmColumn     = $this->_attributeToSql($relation->jm->from, $lmTableAlias);
            }
        }
        else
        {
            $lmColumn = $this->_attributeToSql($relation->lm->column, $lmTableAlias);
        }

        // True for EXISTS, false for NOT EXISTS.
        $not  = $this->not ? 'NOT' : '';

        // Construct subconditions SQL if any.
        $subSql = '';
        $subVal = array();
        if ($this->subconditions)
        {
            list($subSql, $subVal) = $this->subconditions->compile($nCurrent, $useAlias, $toColumn);
            $subSql = 'AND ' . $subSql;
        }

        // Easy subquery.
        // $not contains '' or 'NOT'. It relies on $this->not value.
        $sql = ' ' . $not . ' EXISTS ( SELECT NULL FROM ' . $qLmTable . ' ' .  $qLmTableAlias . ' ' . $join
                    . ' WHERE ' . $lmColumn . ' = ' . $cmColumn . ' '
                    . $subSql . ')';

        return array(
            $sql,
            array_merge($this->flattenValues(), $subVal),
        );
    }

    /**
     *
     * @override.
     */
    public function canMergeWith(Condition $c)
    {
        return parent::canMergeWith($c)
                && $this->exists === $c->exists;
    }

    public function flattenValues()
    {
        $res = array();
        if ($this->subconditions)
        {
            $res = array_merge($res, $this->subconditions->flattenValues());
        }

        return $res;
    }

}

