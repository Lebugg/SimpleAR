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
    /**
     * The option value.
     *
     * @var mixed.
     */
    protected $_value;

    const SYMBOL_COUNT = '#';
    const SYMBOL_NOT   = '!';

    /**
     * Constructor.
     *
     * @param mixed $value The option value.
     */
    public function __construct($value)
    {
        $this->_value = $value;
    }

    public abstract function build($useModel, $model = null);

    public function name()
    {
        return static::$_name;
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
