<?php namespace SimpleAR\Orm\Relation;

use \SimpleAR\Orm\Relation;
use \SimpleAR\Facades\Cfg;

class BelongsTo extends Relation
{
    public function setInformation(array $info)
    {
        parent::setInformation($info);

        $this->cm->attribute = isset($info['key_from'])
            ? $info['key_from']
            : call_user_func(Cfg::get('buildForeignKey'), $this->lm->t->modelBaseName);
            ;

        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        //$this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($info['key_to']) ? $info['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
    }
}
