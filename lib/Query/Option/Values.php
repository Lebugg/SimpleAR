<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

class Values extends Option
{
    public function build()
    {
        return (array) $this->_value;
    }
}
