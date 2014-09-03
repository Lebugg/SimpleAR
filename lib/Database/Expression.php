<?php namespace SimpleAR\Database;

require __DIR__ . '/Expression/Func.php';

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
        $this->setValue($value);
    }

    /**
     * Get expression value.
     *
     * @return mixed
     */
    public function val()
    {
        return $this->_value;
    }

    /**
     * Set expression value.
     *
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->_value = $value;
    }
}
