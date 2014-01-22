<?php
namespace SimpleAR\Query;

require 'options/Conditions.php';
require 'options/Fields.php';
require 'options/Filter.php';
require 'options/GroupBy.php';
require 'options/Has.php';
require 'options/Limit.php';
require 'options/Offset.php';
require 'options/OrderBy.php';
require 'options/Values.php';
require 'options/With.php';

use \SimpleAR\MalformedOptionException;
use \SimpleAR\Query;

abstract class Option
{
    protected $_value;
    protected $_context;
    protected $_arborescence;

    protected static $_optionToClass = array(
        'conditions' => 'Conditions',
        'fields'     => 'Fields',
        'filter'     => 'Filter',
        'group_by'   => 'GroupBy',
        'has'        => 'Has',
        'limit'      => 'Limit',
        'offset'     => 'Offset',
        'order_by'   => 'OrderBy',
        'values'     => 'Values',
        'with'       => 'With',
    );

    const SYMBOL_COUNT = '#';

    public function __construct($value, $context, $arborescence = null)
    {
        $this->_value        = $value;
        $this->_context      = $context;
        $this->_arborescence = $arborescence ?: (isset($context->arborescence) ?  $context->arborescence : null);
    }

    public abstract function build();

    public static function forge($optionName, $value, $context, $arborescence = null)
    {
        if (!isset(self::$_optionToClass[$optionName]))
        {
            throw new MalformedOptionException('Use of unknown option "' .  $optionName . '".');
        }

        $sClass = '\SimpleAR\Query\Option\\' .  self::$_optionToClass[$optionName];

        return new $sClass($value, $context, $arborescence);
    }

    /**
     * Return an attribute object.
     *
     * @param string $attribute    The raw attribute string of the option.
     * @param bool   $relationOnly If true, it tells that the attribute string
     * must contain relation names only.
     *
     * @return StdClass
     *
     * Returned object format:
     *  - relations:    Array of relations contained in attribute;
     *  - lastRelation: The last relation name;
     *  - attribute:    The actual attribute name;
     *  - specialChar:  A special char that can be put at the beginning of the
     *  actual attribute name;
     *  - original:     The orignal string: copy of $attribute.
     */
    protected static function _parseAttribute($attribute, $relationOnly = false)
    {
        // Keep a trace of the original string. We won't touch it.
        $originalString = $attribute;
        $specialChar    = null;

        $pieces = explode('/', $attribute);

        $attribute    = array_pop($pieces);
        $lastRelation = array_pop($pieces);

        if ($lastRelation)
        {
            $pieces[] = $lastRelation;
        }

        $tuple = explode(',', $attribute);
        // We are dealing with a tuple of attributes.
        if (isset($tuple[1]))
        {
            $attribute = $tuple;
        }
        else
        {
            // $attribute = $attribute.

            // (ctype_alpha tests if charachter is alphabetical ([a-z][A-Z]).)
            $specialChar = ctype_alpha($attribute[0]) ? null : $attribute[0];

            // There is a special char before attribute name; we want the
            // attribute's real name.
            if ($specialChar)
            {
                $attribute = substr($attribute, 1);
            }
        }

        if ($relationOnly)
        {
            if (is_array($attribute))
            {
                throw new \SimpleAR\Exception('Cannot have multiple attributes in “' . $originalString . '”.');
            }

            // We do not have attribute name. We only have an array of relation
            // names.
            $pieces[]  = $attribute;
            $attribute = null;
        }

        return (object) array(
            'relations'    => $pieces,
            'lastRelation' => $lastRelation,
            'attribute'    => $attribute,
            'specialChar'  => $specialChar,
            'original'     => $originalString,
        );
    }

}
