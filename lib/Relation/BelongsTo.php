<?php namespace SimpleAR\Relation;

use \SimpleAR\Relation;
use \SimpleAR\Facades\Cfg;

class BelongsTo extends Relation
{
    protected function __construct($a, $cmClass)
    {
        parent::__construct($a, $cmClass);

        $this->cm->attribute = isset($a['key_from'])
            ? $a['key_from']
            : call_user_func(Cfg::get('buildForeignKey'), $this->lm->t->modelBaseName);
            ;

        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
    }


    public function joinAsLast($conditions, $cmAlias, $lmAlias, $joinType)
    {
		foreach ($conditions as $condition)
		{
            foreach ($condition->attributes as $a)
            {
                if ($a->name !== 'id') {
                    return $this->joinLinkedModel($cmAlias, $lmAlias, $joinType);
                }
            }
		}
    }
}
