<?php namespace SimpleAR\Database\Condition;

use SimpleAR\Database\Condition;
use SimpleAR\Database\Arborescence;
use SimpleAR\Exception;
use SimpleAR\Orm\Model;

class Simple extends Condition
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
        'LIKE' => 'LIKE',
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
    public function __construct($attribute, $value, $operator = self::DEFAULT_OP, $logic = 'or')
    {
        $this->setLogic($logic);
        $this->setOperator($operator);
        $this->setAttribute($attribute);
        $this->setValue($value);
    }

    public function setLogic($logic)
    {
        if ($logic !== 'or' && $logic !== 'and')
        {
            throw new Exception('Logical operator "' . $logic . '" is not valid.');
        }

        $this->logic = $logic;
    }

    public function setOperator($operator)
    {
        if (! $operator)
        {
            $operator = self::DEFAULT_OP;
        }

        elseif (! isset(self::$_operators[$operator]))
        {
            $message  = 'Unknown SQL operator: "' . $operator .  '".' .  PHP_EOL;
            $message .= 'List of available operators: ' . implode(', ', array_keys(self::$_operators)); 

            throw new Exception($message);
        }

        $this->operator = $operator;
    }

    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
    }

    // Set value.
    // $value can be: an object, a scalar value or an array.
    // If $value is an array, it can contain: objects, scalar values or 1-dimension array.
    public function setValue($value)
    {
        // Specific case: applying (array) on object would transform it, not wrap it.
        if (is_object($value)) {
            if ($value instanceof Model)
            {
                $value = $value->id;
            }

            // If $value is an Expression object. Leave as it is until the SQL
            // generation.
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

            if (! isset($value[0]))
            {
                $value    = NULL;
                $this->operator = $this->operator === '=' ? 'IS' : 'IS NOT';
                //throw new Exception('Invalid condition value: ' . $value . '.');
            }

            // Several values, we have to *arrayfy* the operator.
            if (isset($value[1]))
            {
                $this->operator = self::$_operators[$this->operator];
            }
            // Else, we do not need an array.
            else
            {
                $value = $value[0];
            }
        }

        $this->value = $value;
    }

    public function compile(Arborescence $root, $useAlias = false, $toColumn = false)
    {
        $nCurrent = $root->find($this->relations);

        $tableAlias = $useAlias ? $nCurrent->alias : '';
        $table      = $toColumn ? $nCurrent->table : null;

        $lhs = $this->_attributeToSql($this->attribute, $tableAlias, $table);
        $op  = $this->_operatorToSql($this->operator);
        $rhs = $this->_valueToSql($this->value);

        $sql = $lhs . ' ' . $op . ' ' . $rhs;
        $val = $this->flattenValues();

        return array($sql, $val);
    }
}
