<?php namespace SimpleAR\Orm\Relation;

use \SimpleAR\Orm\Relation;
use \SimpleAR\Facades\Cfg;

class ManyMany extends Relation
{
    public $jm;

    protected $_toMany = true;

    public function setInformation(array $info)
    {
        parent::setInformation($info);

        $this->cm->attribute = isset($info['key_from']) ? $info['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        //$this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($info['key_to']) ? $info['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
        //$this->lm->pk        = $this->lm->t->primaryKey;

        $this->jm = new \StdClass();

        if (isset($info['join_model']))
        {
            $this->jm->class = $s = $info['join_model'] . Cfg::get('modelClassSuffix');
            $this->jm->t     = $s::table();
            $this->jm->table = $this->jm->t->name;
            $this->jm->from  =
            $this->jm->t->columnRealName(isset($info['join_from']) ?
            $info['join_from'] : call_user_func(Cfg::get('buildForeignKey'), $this->cm->t->modelBaseName));
            $this->jm->to    = $this->jm->t->columnRealName(isset($info['join_to'])
            ? $info['join_to']   : call_user_func(Cfg::get('buildForeignKey'), $this->lm->t->modelBaseName));
        }
        else
        {
            $this->jm->class = null;
            $this->jm->table = isset($info['join_table']) ? $info['join_table'] : $this->cm->table . '_' . $this->lm->table;
            $this->jm->from  = isset($info['join_from'])  ? $info['join_from']  : (strtolower($this->cm->t->name) . '_id');
            $this->jm->to    = isset($info['join_to'])    ? $info['join_to']    : (strtolower($this->lm->t->name) . '_id');
        }

        //$this->jm->alias = '_' . strtolower($this->jm->table);
    }

    // public function deleteJoinModel($value)
    // {
    //     $query = Query::delete(array($this->jm->from => $value), $this->jm->table);
    //     $query->run();
    // }
    //
    // public function deleteLinkedModel($value)
    // {
    //     $lHS = Condition::leftHandSide($this->lm->pk, 'a');
    //     $rHS = Condition::leftHandSide($this->jm->to, 'b');
    //     $condition = $lHS . ' = ' . $rHS;
    //
    //     $query =  "DELETE FROM {$this->lm->table} a
    //                 WHERE EXISTS (
    //                     SELECT NULL
    //                     FROM {$this->jm->table} b
    //                     WHERE b." . implode(' = ? AND b.', $this->jm->from) ." = ?
    //                     AND $condition
    //                 )";
    //
    //     DB::query($query, $value);
    // }
    //
    // public function joinLinkedModel($cmAlias, $lmAlias, $joinType)
    // {
    //     return $this->_joinJM($cmAlias, $lmAlias, $joinType) . ' ' . $this->_joinLM($cmAlias, $lmAlias, $joinType);
    // }
    //
    // public function joinAsLast($conditions, $cmAlias, $lmAlias, $joinType)
    // {
    //     $res = '';
    //
    //    // We always want to join the middle table.
    //     $res .= $this->_joinJM($cmAlias, $lmAlias, $joinType);
    //
	// 	foreach ($conditions as $condition)
	// 	{
    //         foreach ($condition->attributes as $a)
    //         {
    //             // And, under certain conditions, the linked table.
    //             if ($a->logic !== 'or' || $a->name !== 'id')
    //             {
    //                 $res .= $this->_joinLM($cmAlias, $lmAlias, $joinType);
    //                 break 2;
    //             }
    //         }
	// 	}
    //
    //     return $res;
    // }
    //
	public function reverse()
	{
		$relation = clone $this;

		$relation->name = $this->name . '_r';

		$relation->cm  = clone $this->lm;
		$relation->lm  = clone $this->cm;

        $relation->jm       = clone $this->jm;
		$relation->jm->from = $this->jm->to;
		$relation->jm->to   = $this->jm->from;

        return $relation;
	}

    // private function _joinJM($cmAlias, $lmAlias, $joinType)
    // {
    //     $jmAlias = $lmAlias . '_middle';
    //
	// 	return $this->_buildJoin($joinType, $cmAlias, $this->jm->table, $jmAlias, $this->cm->column, $this->jm->from);
    // }
    //
    // private function _joinLM($cmAlias, $lmAlias, $joinType)
    // {
    //     $jmAlias = $lmAlias . '_middle';
    //
	// 	return $this->_buildJoin($joinType, $jmAlias, $this->lm->table, $lmAlias, $this->jm->to, $this->lm->pk);
    // }
    //
}
