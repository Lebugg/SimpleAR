<?php namespace SimpleAR\Database;
/**
 * This file contains the Expression class.
 */

/**
 * Represent a raw SQL expresion.
 *
 * @author Lebugg
 */
class Expression
{
    /**
     * @var mixed The expression value.
     */
    protected $_value;

    public function __construct($value)
    {
        $this->_value = $value;
    }

    public function val()
    {
        return $this->_value;
    }
}
