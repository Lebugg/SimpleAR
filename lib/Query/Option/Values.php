<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

class Values extends Option
{
    protected static $_name = 'values';

    public $values;

    public function build($useModel, $model = null)
    {
        $this->values = (array) $this->_value;
    }
}
