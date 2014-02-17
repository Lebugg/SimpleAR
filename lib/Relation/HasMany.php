<?php namespace SimpleAR\Relation;

use \SimpleAR\Relation;

class HasMany extends Relation
{
    protected function __construct($a, $cMClass)
    {
        parent::__construct($a, $cMClass);
        
        $this->cm->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = (isset($a['key_to']))
            ? $a['key_to']
            : call_user_func(Cfg::get('buildForeignKey'), $this->cm->t->modelBaseName);
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
    }

    public function joinAsLast($conditions, $depth, $joinType)
    {
		foreach ($conditions as $condition)
		{
            foreach ($condition->attributes as $a)
            {
                if ($a->logic !== 'or') {
                    return $this->joinLinkedModel($depth, $joinType);
                }
            }
		}
    }

}