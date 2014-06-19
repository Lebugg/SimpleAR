<?php namespace SimpleAR\Database\Option;

use \SimpleAR\Database\Option;

class Values extends Option
{
    protected static $_name = 'values';

    public $values;

    public function build($useModel, $model = null)
    {
        $this->values = (array) $this->_value;
    }
}
