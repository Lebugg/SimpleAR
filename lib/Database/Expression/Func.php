<?php namespace SimpleAR\Database\Expression;

use \SimpleAR\Database\Expression;

/**
 * An aggregate expression allows a clean syntax to use an SQL aggregate 
 * function on a model attribute.
 *
 * For example, it allows syntax like:
 *
 *  `$query->where(DB::sum('views'), '>', 10);`
 *
 *  or
 *
 *  `$query->whereBetween(DB::avg('friends/age'), [70, 80]);`
 *
 * Differences with using `DB::raw('AVG(friends.age)')` are:
 *
 * - Expression content will be parsed: Query Builder (QB) will perform
 * needed joins automatically and use every other usual features for attribute
 * handling.
 * - It looks nicer!
 *
 * @author Lebugg
 */
class Func extends Expression
{
    /**
     * The extended attribute string given by user.
     *
     * It replaces parent::$_value for input.
     *
     * @var string
     */
    protected $_attribute;

    /**
     * The SQL function.
     *
     * @var string
     */
    protected $_fn;

    public function __construct($attribute, $fn)
    {
        $this->_attribute = $attribute;
        $this->_fn = $fn;
    }

    public function getAttribute()
    {
        return $this->_attribute;
    }

    public function getFunc()
    {
        return $this->_fn;
    }
}
