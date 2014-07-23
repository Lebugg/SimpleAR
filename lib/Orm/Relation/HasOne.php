<?php namespace SimpleAR\Orm\Relation;

use \SimpleAR\Orm\Relation;
use \SimpleAR\Facades\Cfg;

class HasOne extends Relation
{
    public function setInformation(array $info)
    {
        parent::setInformation($info);

        $this->cm->attribute = isset($info['key_from']) ? $info['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        //$this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($info['key_to'])
            ? $info['key_to']
            : call_user_func(Cfg::get('buildForeignKey'), $this->cm->t->modelBaseName);
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
    }
}
