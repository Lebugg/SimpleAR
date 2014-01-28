<?php
namespace SimpleAR\Query\Condition;

use SimpleAR\Query\Condition;

use SimpleAR\Exception;

class Attribute
{
    protected static $_aOperators = array(
        '='  => 'IN',
        '!=' => 'NOT IN',
        '<'  => '< ANY',
        '>'  => '> ANY',
        '<=' => '<= ANY',
        '>=' => '>= ANY',
        'IN' => 'IN',
        'NOT IN' => 'NOT IN',
    );

    const DEFAULT_OP = '=';

    public $name;
    public $value;
    public $operator;
    public $logic;

    public function __construct($name, $value, $operator = null, $logic = 'or')
    {
        // Set logic and check its validity.
        if ($logic !== 'or' && $logic !== 'and')
        {
            throw new Exception('Logical operator "' . $logic . '" is not valid.');
        }

        // Set operator and check its validity.
        $operator  = $operator ?: self::DEFAULT_OP;

        if (! isset(self::$_aOperators[$operator]))
        {
            $sMessage  = 'Unknown SQL operator: "' . $operator .  '".' .  PHP_EOL;
            $sMessage .= 'List of available operators: ' . implode(', ', array_keys(self::$_aOperators)); 

            throw new Exception($sMessage);
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
                $operator = self::$_aOperators[$operator];
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
}
