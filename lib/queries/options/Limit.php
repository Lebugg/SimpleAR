<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

class Limit extends Option
{
    public function build()
    {
        $this->_value = (int) $this->_value;

        if ($this->_value < 0)
        {
            throw new \SimpleAR\MalformedOptionException('"limit" option value must be a natural integer. Negative integer given: ' . $this->_value . '.');
        }

        return $this->_value;
    }
}
