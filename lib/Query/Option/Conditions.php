<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;

use \SimpleAR\Query\Arborescence;

use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\Attribute;
use \SimpleAR\Query\Condition\ConditionGroup;
use \SimpleAR\Query\Condition\SimpleCondition;
use \SimpleAR\Query\Condition\RelationCondition;

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
    public $conditions;
    public $havings  = array();
    public $groups = array();
    public $columns  = array();

    public function build()
    {
        $this->conditions = $this->_parse($this->_value, $this->_arborescence);
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
    protected function _condition($attribute, $operator, $value, Arborescence $arborescence = null)
    {
        // If we are not using model, treatment is much more simple.
        if (! $this->_context->useModel)
        {
            $condition = new SimpleCondition();
            $condition->addAttribute(new Attribute($attribute, $value, $operator));
            $condition->tableAlias = $this->_context->rootTableAlias;

            return $condition;
        }

        $attribute = Attribute::parse($attribute);

        // Special attributes check.
        if ($c = $attribute->specialChar)
        {
            switch ($c)
            {
                case Option::SYMBOL_COUNT:
                    //$this->_having($attribute, $operator, $value);
                    return;
                default:
                    throw new Exception('Unknown symbol “' . $c . '” in attribute “' . $attribute->original . '”.');
                    break;
            }
        }

        $relations = $attribute->relations;
        $node = $arborescence->add($relations);
        $attr = $attribute->attribute;

        $res = array();

        $attribute = new Attribute($attr, $value, $operator);

        // We do not test on $node->relation because of Has subqueries.
        if (! $node->isRoot())
        {
            $condition = new RelationCondition();
            $condition->relation = $node->relation;
        }
        else
        {
            $condition = new SimpleCondition();
        }
        $condition->addAttribute($attribute);

        // Is there a Model's method to handle this attribute? Useful for virtual attributes.
        if (is_string($attr))
        {
            $cmClass = $node->relation ? $node->relation->lm->class : $this->_context->rootModel;
            $method  = 'to_conditions_' . $attr;

            if (method_exists($cmClass, $method))
            {
                if ($subconditions = $cmClass::$method($attribute))
                {
                    return $this->_parse($subconditions, $node);
                }
            }
        }

        $condition->depth      = $node->depth;
        $condition->table      = $node->table;
        $condition->tableAlias = $node->table->alias;

        // Arborescence needs it to know if it useful to join some tables or
        // not.
        $node->addCondition($condition);

        return $condition;
    }

    protected function _having($attribute, $operator, $value)
    {
        // Add related model(s) in join arborescence.
        //
        // We couldn't use second parameter of Attribute::parse() in
        // build() to specify that there must be relations only in the raw
        // attribute because we weren't able to know if it was an "order by
        // count" case.
        $attribute->relations[] = $attribute->attribute;

        $node     = $this->_arborescence->add($attribute->relations, Arborescence::JOIN_LEFT, true);
        // Note: $node->relation cannot be null (because $attribute->relations
        // is never empty).
        $relation = $node->relation;

        // Depth string to suffix table alias if used.
        $depth = (string) ($node->depth ?: '');

        $operator = $operator ?: Attribute::DEFAULT_OP;

        $column = $relation->lm->t->primaryKeyColumns;
        // We assure that column is a string because it might be an array (in
        // case of relationship over several attributes) and we need only one
        // field for the COUNT().
        $column = is_string($column) ? $column : $column[0];

        $tableAlias = $this->_context->useAlias
            ? '`' . $relation->lm->t->alias . $depth . '`.'
            : '';

        // What we put inside the COUNT.
        $countAttribute = $this->_context->useAlias
            ? '`' . $relation->lm->t->alias . $depth . '`.`' . $column . '`'
            : '`' . $column . '`'
            ;

        // What will be returned by Select query.
        $resultAttribute = $this->_context->useResultAlias
            ? '`' . ($attribute->lastRelation ?: $this->_context->rootResultAlias) . '.' . self::SYMBOL_COUNT . $attribute->attribute . '`'
            : '`' . self::SYMBOL_COUNT . $attribute->attribute . '`'
            ;

        // Count alias: `<result alias>.#<relation name>`;
        $this->columns[] = 'COUNT(' . $countAttribute . ') AS ' . $resultAttribute;

        // No need to handle ($previousDepth == -1) case. We would not be in
        // this function: there is at least one relation specified in attribute.
        // And first relation has a depth of 1. So $previousDepth minimum is 0.
        $previousDepth = (string) ($node->depth - 1 ?: '');

        // We have to group rows on something if we want the COUNT to make
        // sense.
        $tableToGroupOn = $node->parent ? $node->parent->relation->cm->t : $this->_context->rootTable;
        $tableAlias     = $this->_context->useAlias ? '`' . $tableToGroupOn->alias . $previousDepth . '`.' : '';
        foreach ((array) $tableToGroupOn->primaryKeyColumns as $column)
        {
            $this->groups[] = $tableAlias . '`' . $column . '`';
        }

        // I don't think it is dangerous to directly write value in HAVING
        // clause => need proof.
        $this->havings[]  = $countAttribute . $operator . ' ' . $value;
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
     *  conditions. See that as SQL surrouding parenthesis;
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
    protected function _parse(array $conditions, Arborescence $arborescence = null)
    {
        $root = $currentAndGroup = new ConditionGroup(ConditionGroup::T_AND);

        foreach ($conditions as $key => $value)
        {
            // It is bound to be a condition. 'myAttribute' => 'myValue'
            if (is_string($key))
            {
                $conditionOrParsedArray = $this->_condition($key, null, $value, $arborescence);

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
                        $root = new ConditionGroup(ConditionGroup::T_OR);
                    }

                    // Add current group.
                    if (! $currentAndGroup->isEmpty())
                    {
                        $root->add($andGroup);
                    }

                    // And initialize a new one.
                    $currentAndGroup = new ConditionGroup(ConditionGroup::T_AND);
                    continue;
                }
                // It means that the AND logical operator is useless to write in
                // a condition array. It is used by default.
                elseif($value === Condition::LOGICAL_OP_AND)
                {
                    continue;
                }

                // Condition or condition group.
                else
                {
                    // Condition.
                    if (isset($value[0]) && is_string($value[0]))
                    {
                        $conditionOrParsedArray = $this->_condition($value[0], $value[1], $value[2], $arborescence);

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
                        $res = $this->_parse($value, $arborescence);

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
