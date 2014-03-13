<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;
use \SimpleAR\Query\Condition as Condition;
use \SimpleAR\Database\Expression;

use \SimpleAR\Exception;

/**
 *
 * We accept two forms of conditions:
 * 1) Basic conditions:
 *  ```php
 *      array(
 *          'my/attribute' => 'myValue',
 *          ...
 *      )
 *  ```
 * 2) Conditions with operator:
 *  ```php
 *      array(
 *          array('my/attribute', 'myOperator', 'myValue'),
 *          ...
 *      )
 *  ```
 *
 * Of course, you can combine both form in a same condition array.
 *
 * By default, conditions are linked with a AND operator but you can use an
 * OR by specifying it in condition array:
 *  ```php
 *      array(
 *          'attr1' => 'val1',
 *          'OR',
 *          'attr2' => 'val2',
 *          'attr3' => 'val3,
 *      )
 *  ```
 *
 * This correspond to the following exhaustive array:
 *  ```php
 *      array(
 *          'attr1' => 'val1',
 *          'OR',
 *          'attr2' => 'val2',
 *          'AND',
 *          'attr3' => 'val3,
 *      )
 *  ```
 *
 * You can nest condition arrays. Example:
 *  ```php
 *      array(
 *          array(
 *              'attr1' => 'val1',
 *              'attr2' => 'val2',
 *          )
 *          'OR',
 *          array(
 *              'attr3' => 'val3,
 *              'attr1' => 'val4,
 *          )
 *      )
 *  ```
 *
 * So we come with this condition array syntax tree:
 *  ```php
 *  condition_array:
 *      array(
 *          [condition | condition_array | (OR | AND)] *
 *      );
 *
 *  condition:
 *      [
 *          'attribute' => 'value'
 *          |
 *          array('attribute', 'operator', 'value')
 *      ]
 *  attribute: <string>
 *  operator: <string>
 *  value: <mixed>
 *  ```
 *
 * Operators: =, !=, IN, NOT IN, >, <, <=, >=.
 *
 * @param array $array The condition array to parse.
 *
 * @return array A well formatted condition array.
 */
class Conditions extends Option
{
    protected static $_name = 'conditions';

    public $where;
    public $aggregates = array();
    public $groups     = array();
    public $havings    = array();
    public $joins      = array();

    public function build($useModel, $model = null)
    {
        $this->where = $this->_parseConditionArray($this->_value);
    }

    /**
     * Handle a condition.
     *
     * @param stdClass $attribute An attribute object returned by
     * Attribute::parse().
     * @param string   $operator  A logical operator 'OR' or 'AND'.
     * @param mixed    $value     The condition value (scalar value or array).
     *
     * @see _attribute()
     */
    protected function _buildCondition($attribute, $operator, $value)
    {
        $relations = explode('/', $attribute);
        $attribute = array_pop($relations);

        switch ($attribute[0])
        {
            case Option::SYMBOL_COUNT:
                $attribute = substr($attribute, 1);
                $this->aggregates[] = array(
                    'relations' => array_merge($relations, array($attribute)),
                    'attribute' => 'id',
                    'toColumn'  => true,
                    'fn'        => 'COUNT',

                    'asRelations' => $relations,
                    'asAttribute' => self::SYMBOL_COUNT . $attribute,
                );

                $this->havings[] = array(
                    'asRelations' => $relations,
                    'asAttribute' => self::SYMBOL_COUNT . $attribute,
                    'operator'    => $operator ?: '=',
                    'value'       => $value,
                );

                $this->groups[] = array(
                    'relations' => $relations,
                    'attribute' => 'id',
                    'toColumn'  => true,
                );
                return;
        }

        // Ok, classic condition.

        $condition = new Condition\Simple($attribute, $value, $operator);
        $condition->relations = $relations;

        $this->joins[] = $relations;

        // Is there a Model's method to handle this attribute? Useful for virtual attributes.
        /* if (is_string($attr)) */
        /* { */
        /*     $cmClass = $node->relation ? $node->relation->lm->class : $this->_->rootModel; */
        /*     $method  = 'to_conditions_' . $attr; */

        /*     if (method_exists($cmClass, $method)) */
        /*     { */
        /*         if ($subconditions = $cmClass::$method($attribute)) */
        /*         { */
        /*             return $this->parseConditionArray($subconditions, $node); */
        /*         } */
        /*     } */
        /* } */

        // Arborescence needs it to know if it useful to join some tables or
        // not.

        return $condition;
    }

    /**
     * Parse a user condition array (that is the raw "conditions" option given
     * by user).
     *
     * Algorithm
     * ---------
     * For each array entry, we check what it is:
     *
     *  - A condition;
     *
     *  - An array, that is an array of conditions. Can be used to "group"
     *  conditions. See that as SQL surrounding parenthesis;
     *
     *  - A logical operator: "AND" or "OR" by default.
     *      @see Condition::LOGICAL_OP_AND
     *      @see Condition::LOGICAL_OP_OR
     *  
     * @param array         $conditions     The condition array to parse.
     * @param Arborescence  $arborescence   The current arborescence node. This
     * function will be called recursively.
     *
     * @return array
     */
    protected function _parseConditionArray(array $conditions)
    {
        $root = $currentAndGroup = new Condition\Group(Condition\Group::T_AND);

        foreach ($conditions as $key => $value)
        {
            // It is bound to be a condition. 'myAttribute' => 'myValue'
            if (is_string($key))
            {
                $conditionOrParsedArray = $this->_buildCondition($key, null, $value);

                // The condition may have been transformed into a HAVING clause.
                //
                // In these cases, _condition() returns nothing.
                if ($conditionOrParsedArray)
                {
                    $currentAndGroup->add($conditionOrParsedArray);
                }
            }

            // It can be a condition, a condition group, or a logical operator.
            else
            {
                // It is an OR logical operator. Next conditions will be part of
                // another group of conditions.
                if ($value === Condition::LOGICAL_OP_OR)
                {
                    // Should happen only once.
                    if ($root === $currentAndGroup)
                    {
                        $root = new Condition\Group(Condition\Group::T_OR);
                    }

                    // Add current group.
                    if (! $currentAndGroup->isEmpty())
                    {
                        $root->add($currentAndGroup);
                    }

                    // And initialize a new one.
                    $currentAndGroup = new Condition\Group(Condition\Group::T_AND);
                    continue;
                }

                // It means that the AND logical operator is useless to write in
                // a condition array. It is used by default.
                elseif($value === Condition::LOGICAL_OP_AND)
                {
                    continue;
                }

                elseif ($value instanceof Expression)
                {
                    $conditionOrParsedArray = $this->_buildCondition(null, null, $value);

                    // The condition may have been transformed into a HAVING clause.
                    //
                    // In these cases, _condition() returns nothing.
                    if ($conditionOrParsedArray)
                    {
                        $currentAndGroup->add($conditionOrParsedArray);
                    }
                }

                // Condition or condition group.
                else
                {
                    // Condition.
                    if (isset($value[0]) && is_string($value[0]))
                    {
                        $conditionOrParsedArray = $this->_buildCondition($value[0], $value[1], $value[2]);

                        // The condition may have been transformed into a HAVING clause.
                        //
                        // In these cases, _condition() returns nothing.
                        if ($conditionOrParsedArray)
                        {
                            $currentAndGroup->add($conditionOrParsedArray);
                        }
                    }

                    // Condition group.
                    else
                    {
                        $res = $this->_parseConditionArray($value);

                        if ($currentAndGroup->isEmpty())
                        {
                            $currentAndGroup = $res;
                        }
                        else
                        {
                            $currentAndGroup->add($res);
                        }
                    }
                }
            }
        }

        if ($root !== $currentAndGroup
            && ! $currentAndGroup->isEmpty())
        {
            $root->add($currentAndGroup);
        }

        return $root;
    }
}
