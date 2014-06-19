<?php namespace SimpleAR\Database\Option;

use \SimpleAR\Database\Option;
use \SimpleAR\Exception\MalformedOption;

class Limit extends Option
{
    protected static $_name = 'limit';

    public $limit;

    public function build($useModel, $model = null)
    {
        $this->_value = (int) $this->_value;

        if ($this->_value < 0)
        {
            throw new MalformedOption('"limit" option value must be a natural integer. Negative integer given: ' . $this->_value . '.');
        }

        $this->limit = $this->_value;
    }
}
