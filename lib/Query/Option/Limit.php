<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Exception\MalformedOption;


class Limit extends Option
{
    public $limit;

    public function build()
    {
        $this->_value = (int) $this->_value;

        if ($this->_value < 0)
        {
            throw new MalformedOption('"limit" option value must be a natural integer. Negative integer given: ' . $this->_value . '.');
        }

        $this->limit = $this->_value;
    }
}
