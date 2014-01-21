<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\SimpleCondition;
use \SimpleAR\Query\Condition\RelationCondition;

class Conditions extends Option
{
    public function build()
    {
        $conditions = $this->_parse($this->_value);

        call_user_func($this->_callback, $conditions);
    }

    /**
     * Handle a condition.
     *
     * @param stdClass $attribute An attribute object returned by
     * _parseAttribute().
     * @param string   $operator  A logical operator 'OR' or 'AND'.
     * @param mixed    $value     The condition value (scalar value or array).
     *
     * @see _attribute()
     */
    protected function _condition($attribute, $operator, $value)
    {
        // Special attributes check.
        if ($c = $attribute->specialChar)
        {
            switch ($c)
            {
                case Option::SYMBOL_COUNT:
                    //$this->_having($attribute, $operator, $value);
                    return;
                default:
                    throw new \SimpleAR\Exception('Unknown symbol “' . $c . '” in attribute “' . $attribute->original . '”.');
                    break;
            }
        }

        $node = $this->_arborescence->add($attribute->relations);
        $attr = $attribute->attribute;

        if ($node->relation)
        {
            $condition = new RelationCondition($attr, $operator, $value);
            $condition->relation = $node->relation;
        }
        else
        {
            $condition = new SimpleCondition($attr, $operator, $value);
        }
        $condition->depth = $node->depth;
        $condition->table = $node->table;

        // Is there a Model's method to handle this attribute? Useful for virtual attributes.
        if ($this->_context->useModel && is_string($attr))
        {
            $sModel  = $node->relation ? $node->relation->lm->class : $this->_context->rootModel;
            $sMethod = 'to_conditions_' . $attr;

            if (method_exists($sModel, $sMethod))
            {
                if ($a = $sModel::$sMethod($condition))
                {
                    $condition->virtual = true;
                    $condition->subconditions = $this->_parse($a);
                }
            }
        }

        $node->conditions[] = $condition;

        return $condition;
    }

    protected function _having()
    {
    }

    protected function _parse($conditions)
    {
        $res = array();

        $logicalOperator = Condition::DEFAULT_LOGICAL_OP;
        $condition       = null;

        foreach ($conditions as $key => $value)
        {
            // It is bound to be a condition. 'myAttribute' => 'myValue'
            if (is_string($key))
            {
                $condition = $this->_condition(self::_parseAttribute($key), null, $value);
                if ($condition)
                {
                    $res[] = array($logicalOperator, $condition);
                }

                // Reset operator.
                $logicalOperator = Condition::DEFAULT_LOGICAL_OP;
            }

            // It can be a condition, a condition group, or a logical operator.
            else
            {
                // It is a logical operator.
                if     ($value === 'OR'  || $value === '||') { $logicalOperator = 'OR';  }
                elseif ($value === 'AND' || $value === '&&') { $logicalOperator = 'AND'; }

                // Condition or condition group.
                else
                {
                    // Condition.
                    if (isset($value[0]) && is_string($value[0]))
                    {
                        $condition = $this->_condition(self::_parseAttribute($value[0]), $value[1], $value[2]);
                        if ($condition)
                        {
                            $res[] = array($logicalOperator, $condition);
                        }
                    }

                    // Condition group.
                    else
                    {
                        $res[] = array($logicalOperator, $this->_parse($value));
                    }

                    // Reset operator.
                    $logicalOperator = Condition::DEFAULT_LOGICAL_OP;
                }
            }

            $condition = null;
        }

        return $res;
    }
}
