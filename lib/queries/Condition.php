<?php
/**
 * This file contains the Condition class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

require 'conditions/Attribute.php';
require 'conditions/ConditionGroup.php';
require 'conditions/Exists.php';
require 'conditions/Relation.php';
require 'conditions/Simple.php';

use SimpleAR\Query\Condition\Attribute;

use SimpleAR\Exception;

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
abstract class Condition
{
    /**
     * List of available operators.
     *
     * The keys are the available operators; The values are the corresponding operators when
     * condition is made on multiple values.
     *
     * @var array
     */

    const LOGICAL_OP_AND = 'AND';
    const LOGICAL_OP_OR  = 'OR';

    const DEFAULT_LOGICAL_OP = self::LOGICAL_OP_AND;

    public $attributes = array();

    /**
     * A Table object corresponding to the attribute's Model.
     *
     * @var Table
     */
    public $table;
    public $tableAlias = '';
    public $depth      = 0;

    public $relation;

    /**
     * Add an attribute to the condition.
     *
     * @param string|array $attribute Attribute(s) of condition.
     * @param string       $operator  Operator to use.
     * @param mixed        $value     Value(s) to test attribute(s) against.
     * @param string       $logic     The logic of the condition.
     *
     * @throws Exception if operator, value, or logic is invalid.
     */
    public function addAttribute(Attribute $attribute)
    {
        $this->attributes[] = $attribute;
    }

    /**
     * Check that a Condition can safely merged with another.
     *
     * @param Condition $c The Condition to check against.
     *
     * @return bool Returns true when $this condition can be merged with $c.
     */
    public function canMergeWith(Condition $c)
    {
        return $this->relation === $c->relation
                && get_class() === get_class($c);
    }

    public function merge(Condition $c)
    {
        if ($c instanceof SimpleAR\Query\Condition\ConditionGroup)
        {
            foreach ($c->_elements as $element)
            {
                $this->merge($element);
            }
        }
        else
        {
            foreach ($c->attributes as $a)
            {
                $this->addAttribute($a);
            }
        }
    }

    /**
     * This function is used to format value array for PDO.
     *
     * @return array
     *      Condition values.
     */
    public function flattenValues()
    {
        $res = array();

        foreach ($this->attributes as $attribute)
        {
            $value = $attribute->value;

            if ($value === null)
            {
                $res[] = null;
            }
            elseif (is_array($value))
            {
                if (is_array($value[0]))
                {
                    $res = array_merge($res, call_user_func_array('array_merge', $value));
                }
                else
                {
                    $res = array_merge($res, $value);
                }
            }
            else
            {
                $res[] = $value;
            }
        }

        return $res;
    }

    /**
     * Creates the left hand side of an SQL condition.
     *
     * @param string|array  $columns    One or several columns the condition is made on.
     * @param string|Table  $tableAlias The table alias to use to prefix columns.
     *
     * @return string A valid left hand side SQL condition.
     */
    public static function leftHandSide($columns, $tableAlias = '')
    {
        // Add dot to alias to prevent additional concatenations in foreach.
        if ($tableAlias !== '')
        {
            $tableAlias = '`' . $tableAlias . '`.';
        }

        $a = array();
        foreach ((array) $columns as $column)
        {
            $a[] = $tableAlias . '`' . $column . '`';
        }

        // If several conditions, we have to wrap them with brackets in order to assure about
        // conditional operators priority.
        return isset($a[1]) ? '(' . implode(',', $a) . ')' : $a[0];
    }

    /**
     * Creates the right hand side of an SQL condition.
     *
     * Condition is constructed with '?'. So values must be bind to query in some other place.
     * @see Condition::flattenValues()
     *
     * @param mixed $value The value that will be bound to the condition. It
     * can be an simple array, a two-dimensional array or a scalar.
     *
     * @return string A valid right hand side SQL condition.
     */
    public static function rightHandSide($value)
    {
        // First level count.
        $firstLevelCount = count($value);

        // $value is a multidimensional array. Actually, it is a array of
        // tuples.
        if (is_array($value))
        {
            if (is_array($value[0]))
            {
                // Tuple cardinal.
                $tupleSize = count($value[0]);
                
                $tuple = '(' . str_repeat('?,', $tupleSize - 1) . '?)';
                $res   = '(' . str_repeat($tuple . ',', $firstLevelCount - 1) . $tuple .  ')';
            }
            // Simple array.
            else
            {
                $res = '(' . str_repeat('?,', $firstLevelCount - 1) . '?)';
            }
        }
        else
        {
            $res = '?';
        }

        return $res;
    }

    /**
     * This function transforms the Condition object into SQL.
     *
     * @param bool $useAliases Should table aliases be used?
     * @param bool $toColumn   Should attributes be transformed to columns?
     *
     * @retutn string A valid SQL condition string.
     */
    public abstract function toSql($useAliases = true, $toColumn = true);
}
