<?php namespace SimpleAR\Query;

require __DIR__ . '/Option/Conditions.php';
require __DIR__ . '/Option/Fields.php';
require __DIR__ . '/Option/Filter.php';
require __DIR__ . '/Option/Group.php';
require __DIR__ . '/Option/Has.php';
require __DIR__ . '/Option/Limit.php';
require __DIR__ . '/Option/Offset.php';
require __DIR__ . '/Option/Order.php';
require __DIR__ . '/Option/Values.php';
require __DIR__ . '/Option/With.php';

use \SimpleAR\Query;

use \SimpleAR\MalformedOptionException;

abstract class Option
{
    protected $_value;
    protected $_context;
    protected $_arborescence;

    protected static $_optionToClass = array(
        'conditions' => 'Conditions',
        'fields'     => 'Fields',
        'filter'     => 'Filter',
        'group'      => 'Group',
        'group_by'     => 'Group', // @deprecated.
        'has'        => 'Has',
        'limit'      => 'Limit',
        'offset'     => 'Offset',
        'order'      => 'Order',
        'order_by'     => 'Order', // @deprecated.
        'values'     => 'Values',
        'with'       => 'With',
    );

    const SYMBOL_COUNT = '#';
    const SYMBOL_NOT   = '!';

    public function __construct($value, \StdClass $context = null, Arborescence $arborescence = null)
    {
        $this->_value        = $value;
        $this->_context      = $context;
        $this->_arborescence = $arborescence ?: (isset($context->arborescence) ?  $context->arborescence : null);
    }

    public abstract function build();

    public static function forge($optionName, $value, \StdClass $context = null, Arborescence $arborescence = null)
    {
        if (! isset(self::$_optionToClass[$optionName]))
        {
            throw new MalformedOptionException('Use of unknown option "' .  $optionName . '".');
        }

        $class = '\SimpleAR\Query\Option\\' .  self::$_optionToClass[$optionName];

        $option = new $class($value, $context, $arborescence);
        $option->build();

        return $option;
    }

    public function parseRelationString($string)
    {
        $relations    = explode('/', $string);
        $lastRelation = array_pop($relations);

        // (ctype_alpha tests if charachter is alphabetical ([a-z][A-Z]).)
        $specialChar = ctype_alpha($lastRelation[0]) ? null : $lastRelation[0];

        // There is a special char before attribute name; we want the
        // attribute's real name.
        if ($specialChar)
        {
            $lastRelation = substr($lastRelation, 1);
        }

        $allRelations = $relations;
        $allRelations[] = $lastRelation;

        return (object) array(
            'relations'    => $relations,
            'lastRelation' => $lastRelation,
            'allRelations' => $allRelations,
            'specialChar'  => $specialChar,
        );
    }
}
