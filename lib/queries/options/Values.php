<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

class Values extends Option
{
    public function build()
    {
        call_user_func($this->_callback, (array) $this->_value);
    }
}
