<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query;
use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

class With extends Option
{
    public function build()
    {
        $aRes = array();
        $a = (array) $this->_value;

        foreach($a as $sRelation)
        {
            $oNode = $this->_arborescence->add(explode('/', $sRelation), Arborescence::JOIN_LEFT, true);
            $oRelation = $oNode->relation;

            $sLM = $oRelation->lm->class;

            $aColumns = $sLM::columnsToSelect($oRelation->filter);
            $aColumns = Query::columnAliasing($aColumns, $oRelation->lm->alias . ($oNode->depth ?: ''), $sRelation);

            $aRes = array_merge($aRes, $aColumns);
        }

        call_user_func($this->_callback, $aRes);
    }
}
