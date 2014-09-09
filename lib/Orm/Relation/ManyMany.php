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
        $this->cm->column    = (array) $this->cm->t->columnRealName($this->cm->attribute);

        $this->lm->attribute = isset($info['key_to']) ? $info['key_to'] : 'id';
        $this->lm->column    = (array) $this->lm->t->columnRealName($this->lm->attribute);

        $this->jm = new \StdClass();

        if (isset($info['join_model']))
        {
            $this->jm->class = $s = $info['join_model'] . Cfg::get('modelClassSuffix');
            $this->jm->t     = $s::table();
            $this->jm->table = $this->jm->t->name;
            $this->jm->from  = (array) ($this->jm->t->columnRealName(isset($info['join_from'])
                ? $info['join_from']
                : call_user_func(Cfg::get('buildForeignKey'), $this->cm->t->modelBaseName)));
            $this->jm->to = (array) ($this->jm->t->columnRealName(isset($info['join_to'])
                ? $info['join_to']
                : call_user_func(Cfg::get('buildForeignKey'), $this->lm->t->modelBaseName)));
        }
        else
        {
            $this->jm->class = null;
            $this->jm->table = isset($info['join_table']) ? $info['join_table'] : $this->cm->table . '_' . $this->lm->table;

            $this->jm->from  = (array) (isset($info['join_from'])
                ? $info['join_from']
                : (decamelize($this->cm->t->modelBaseName) . '_id'));

            $this->jm->to = (array) (isset($info['join_to'])
                ? $info['join_to']
                : (decamelize($this->lm->t->modelBaseName) . '_id'));
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

    /**
     * Return the name of the middle table.
     *
     * @return string
     */
    public function getMiddleTableName()
    {
        return $this->jm->table;
    }

    /**
     * Return an alias for the middle table.
     *
     * Table alias is generated as follows:
     *
     *  <relation name> . '_m';
     *
     * "_m" stands for "middle".
     *
     * @return string.
     */
    public function getMiddleTableAlias()
    {
        return $this->name . '_m';
    }

    /**
     * Return an associative list of columns on which the relation is made 
     * between current model table and middle table.
     *
     * @see SimpleAR\Orm\Relation::getJoinColumns()
     * @return array
     */
    public function getMiddleJoinColumns()
    {
        $cms = $this->cm->column; $cms = is_array($cms) ? $cms : array($cms);
        $mds = $this->jm->from; $mds = is_array($mds) ? $mds : array($mds);

        $res = array();
        foreach ($cms as $i => $cmCol)
        {
            $res[$cmCol] = $mds[$i];
        }

        return $res;
    }

    /**
     * Return an associative list of columns on which the relation is made 
     * between middle table and linked model table.
     *
     * Note:
     * -----
     *
     * It does *not* return the same thing as parent::getJoinColumns().
     * In parent's, it returns association columns between CM and LM. This one 
     * returns association columns between MD and LM.
     *
     * We override this method to simplify builder code. @see 
     * SimpleAR\Database\Builder\WhereBuilder::_addJoinClause()
     *
     * @see SimpleAR\Orm\Relation::getJoinColumns()
     * @see getMiddleJoinColumns()
     *
     * @return array
     */
    public function getJoinColumns()
    {
        $mds = $this->jm->to; $mds = is_array($mds) ? $mds : array($mds);
        $lms = $this->lm->column; $lms = is_array($lms) ? $lms : array($lms);

        $res = array();
        foreach ($mds as $i => $mdCol)
        {
            $res[$mdCol] = $lms[$i];
        }

        return $res;
    }
}
