<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

class Values extends Option
{
    public $values;

    public function build()
    {
        $this->values = (array) $this->_value;
    }
}
