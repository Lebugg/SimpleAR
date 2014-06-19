<?php namespace SimpleAR\Database\Builder;

use \SimpleAR\Database\Builder;

class WhereBuilder extends Builder
{
    public $availableOptions = array(
        'conditions',
    );

    protected function _buildConditions(array $conditions)
    {
        $this->_query->where = array();
    }
}
