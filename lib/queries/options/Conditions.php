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
     * @param StdClass $attribute An attribute object returned by _attribute().
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
                case '#':
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
                $condition = $this->_condition($this->_attribute($key), null, $value);
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
                        $condition = $this->_condition($this->_attribute($value[0]), $value[1], $value[2]);
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

    /**
     * Return an attribute object.
     *
     * @param string $attribute    The raw attribute string of the condition.
     * @param bool   $relationOnly If true, it tells that the attribute string
     * must contain relation names only.
     *
     * @return StdClass
     */
    protected function _attribute($attribute, $relationOnly = false)
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
                throw new \SimpleAR\Exception('Cannot have multiple attributes in “' . $originalString . '”.');
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

}
