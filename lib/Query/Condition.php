<?php
/**
 * This file contains the Condition class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

require __DIR__ . '/Condition/Attribute.php';
require __DIR__ . '/Condition/Group.php';
require __DIR__ . '/Condition/Exists.php';
require __DIR__ . '/Condition/Relation.php';
require __DIR__ . '/Condition/Simple.php';

use SimpleAR\Query\Condition\Attribute;
use SimpleAR\Database\Expression;
use SimpleAR\Facades\DB;
use SimpleAR\Table;
use SimpleAR\Exception;

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
abstract class Condition
{
    const LOGICAL_OP_AND = 'AND';
    const LOGICAL_OP_OR  = 'OR';

    const DEFAULT_LOGICAL_OP = self::LOGICAL_OP_AND;

    /**
     * The arborescence node on which the node is applied.
     *
     * @var \SimpleAR\Arborescence
     */
    public $node;

    public $subconditions;

    /**
     * Negative form?
     *
     * @var bool
     */
    public $not = false;


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
               && get_class()  === get_class($c);
    }

    /**
     * Return the relation on which this condition is built.
     *
     * We retrieve it through the Arborescence node instance.
     *
     * @param string $cm The current model class' name.
     *
     * @return \SimpleAR\Relation
     */
    public function getRelation($cm)
    {
        return $cm::relation($this->node->relationName);
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

        $value = $this->value;

        if ($value instanceof Expression)
        {
        }
        elseif ($value === null)
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

        if ($this->subconditions)
        {
            $res = array_merge($res, $this->subconditions->flattenValues());
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
    public function leftHandSide($columns, $tableAlias = '')
    {
        // Add dot to alias to prevent additional concatenations in foreach.
        if ($tableAlias !== '')
        {
            $tableAlias = DB::quote($tableAlias) . '.';
        }

        $a = array();
        foreach ((array) $columns as $column)
        {
            $a[] = $tableAlias . DB::quote($column);
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
     * can be:
     *  * a scalar
     *  * an simple array;
     *  * a two-dimensional array
     *  * an Expression object.
     *
     * @return string A valid right hand side SQL condition.
     */
    public function rightHandSide($value)
    {
        if ($value instanceof Expression)
        {
            return $value->val();
        }

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
     */
    public abstract function compile(Arborescence $node, $useAlias = false, $toColumn = false);

    protected function _attributeToSql($attribute, $tableAlias, Table $table = null)
    {
        if ($attribute instanceof Expression)
        {
            return $attribute->val();
        }

        $columns = $table ? $table->columnRealName($attribute) : $attribute;
        return $this->leftHandSide($columns, $tableAlias);
    }

    protected function _operatorToSql($operator)
    {
        return (string) $operator;
    }

    protected function _valueToSql($value)
    {
        if ($value instanceof Expression)
        {
            return $value->val();
        }

        return $this->rightHandSide($value);
    }
}
