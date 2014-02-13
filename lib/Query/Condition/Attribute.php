<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Condition;

use SimpleAR\Exception;
use SimpleAR\Table;

/**
 * This class modelizes a condtion attribue.
 *
 * @author Lebugg
 */
class Attribute
{
    /**
     * Contains all available operators.
     *
     * The keys are the opetors the user can use (the availble aperators) and
     * the values are the corresponding operators when the attribue is tested
     * against several values (automatically used when needed).
     *
     * @var array.
     */
    protected static $_operators = array(
        '='  => 'IN',
        '!=' => 'NOT IN',
        '<'  => '< ANY',
        '>'  => '> ANY',
        '<=' => '<= ANY',
        '>=' => '>= ANY',
    );

    /**
     * The operator to used when no operator is specified.
     */
    const DEFAULT_OP = '=';

    /**
     * One or several attribute name(s).
     *
     * @var string|array.
     */
    public $name;

    /**
     * The value to test the attribute against.
     *
     * @var mixed
     */
    public $value;

    /**
     * Operator of the condition.
     *
     * @var string
     */
    public $operator;

    /**
     * Logic of the condition.
     *
     * @var string
     */
    public $logic;

    /**
     * Constructor.
     *
     * It assures of the validity of the condition.
     *
     * @param string $name      The attribute name.
     * @param mixed  $value     The value.
     * @param string $operator  The operator to use.
     * @param string $logic     The logic of the condition.
     */
    public function __construct($name, $value, $operator = null, $logic = 'or')
    {
        // Set logic and check its validity.
        if ($logic !== 'or' && $logic !== 'and')
        {
            throw new Exception('Logical operator "' . $logic . '" is not valid.');
        }

        // Set operator and check its validity.
        $operator  = $operator ?: self::DEFAULT_OP;

        if (! isset(self::$_operators[$operator]))
        {
            $message  = 'Unknown SQL operator: "' . $operator .  '".' .  PHP_EOL;
            $message .= 'List of available operators: ' . implode(', ', array_keys(self::$_operators)); 

            throw new Exception($message);
        }

        // Set value.
        // $value can be: an object, a scalar value or an array.
        // If $value is an array, it can contain: objects, scalar values or 1-dimension array.

        // Specific case: applying (array) on object would transform it, not wrap it.
        if (is_object($value))
        {
            $value = $value->id;
        }
        else
        {
            $values = $value;
            $value  = array();
            // Extract IDs if objects are passed.
            foreach ((array) $values as $val)
            {
                $value[] = is_object($val) ? $val->id : $val;
            }

            if (!isset($value[0]))
            {
                $value    = NULL;
                $operator = $operator === '=' ? 'IS' : 'IS NOT';
                //throw new Exception('Invalid condition value: ' . $value . '.');
            }

            // Several values, we have to *arrayfy* the operator.
            if (isset($value[1]))
            {
                $operator = self::$_operators[$operator];
            }
            // Else, we do not need an array.
            else
            {
                $value = $value[0];
            }
        }

        $this->name     = $name;
        $this->value    = $value;
        $this->operator = $operator;
        $this->logic    = $logic;

    }

    /**
     * Return an attribute object.
     *
     * @param string $attribute    The raw attribute string of the option.
     * @param bool   $relationOnly If true, it tells that the attribute string
     * must contain relation names only.
     *
     * @return St_class
     *
     * Returned object format:
     *  - relations:    Array of relations contained in attribute;
     *  - lastRelation: The last relation name;
     *  - attribute:    The actual attribute name;
     *  - specialChar:  A special char that can be put at the beginning of the
     *  actual attribute name;
     *  - original:     The orignal string: copy of $attribute.
     */
    public static function parse($attribute, $relationOnly = false)
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
                throw new Exception('Cannot have multiple attributes in “' . $originalString . '”.');
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

    public function toSql($tableAlias = '', Table $t = null)
    {
        $columns = $t ? $t->columnRealName($this->name) : $this->name;

        $lhs = Condition::leftHandSide($columns, $tableAlias);
        $rhs = Condition::rightHandSide($this->value);

        return $lhs . ' ' . $this->operator . ' ' . $rhs;

    }
}
