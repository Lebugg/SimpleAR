<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Exception\MalformedOption;

class Offset extends Option
{
    public $offset;

    public function build()
    {
        $this->_value = (int) $this->_value;

        if ($this->_value < 0)
        {
            throw new MalformedOption('"offset" option value must be a natural integer. Negative integer given: ' . $this->_value . '.');
        }

        $this->offset = $this->_value;
    }
}

