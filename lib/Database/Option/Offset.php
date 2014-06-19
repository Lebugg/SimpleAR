<?php namespace SimpleAR\Database\Option;

use \SimpleAR\Database\Option;
use \SimpleAR\Exception\MalformedOption;

class Offset extends Option
{
    protected static $_name = 'offset';

    public $offset;

    public function build($useModel, $model = null)
    {
        $this->_value = (int) $this->_value;

        if ($this->_value < 0)
        {
            throw new MalformedOption('"offset" option value must be a natural integer. Negative integer given: ' . $this->_value . '.');
        }

        $this->offset = $this->_value;
    }
}

