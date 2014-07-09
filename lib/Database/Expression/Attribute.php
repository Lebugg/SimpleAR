<?php namespace SimpleAR\Database\Expression;
/**
 * This file contains the Attribute class.
 */

/**
 * Represent a query condition attribute.
 *
 * @author Lebugg
 */
class Attribute extends Expression
{
    /**
     * The table alias.
     *
     * @var string
     */
    public $tableAlias;

    /**
     * The attribute name.
     *
     * @var string
     */
    public $attribute;

    public function __construct($tableAlias, $attribute)
    {
        $this->tableAlias = $tableAlias;
        $this->attribute  = $attribute;
    }
}
