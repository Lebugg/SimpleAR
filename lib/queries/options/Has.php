<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Option\Conditions;

use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\Attribute;
use \SimpleAR\Query\Condition\ConditionGroup;
use \SimpleAR\Query\Condition\ExistsCondition;

use \SimpleAR\MalformedOptionException;

use \SimpleAR\Query\Arborescence;

class Has extends Conditions
{
    public function build()
    {
        $conditions = $this->_parseHas($this->_value, $this->_arborescence);

        return array(
            'conditions' => $conditions,
            'havings'    => $this->_havings,
            'groupBys'   => $this->_groupBys,
            'selects'    => $this->_selects,
        );
    }

    /**
     * Handle a "has condition".
     *
     * @param stdClass $attribute An attribute object returned by
     * Option::_parseAttribute().
     * @param array    $conditions Optional conditions to add to the "has
     * condition".
     *
     * @return ExistsCondition
     */
    protected function _has($attribute, Arborescence $arborescence, array $conditions = null)
    {
        $attribute = self::_parseAttribute($attribute, true);

        $node = $arborescence->add($attribute->relations);
        $has = new ExistsCondition($attribute->attribute, null, null);

        $has->depth    = $node->depth;
        $has->exists   = $attribute->specialChar !== self::SYMBOL_NOT;
        $has->relation = $node->relation;

        $node->addCondition($has);

        if ($conditions)
        {
            $node->parent = null;
            $has->subconditions = $this->_parse($conditions, $node);
        }

        return $has;
    }


    public function _parseHas($has, $arborescence)
    {
        $orGroup  = new ConditionGroup(ConditionGroup::T_OR);
        $andGroup = new ConditionGroup(ConditionGroup::T_AND);

        foreach ($has as $key => $value)
        {
            // It is a logical operator.
            if ($value === Condition::LOGICAL_OP_OR)
            {
                // Add current group.
                if (! $andGroup->isEmpty())
                {
                    $orGroup->add($andGroup);
                }

                // And initialize a new one.
                $andGroup = new ConditionGroup(ConditionGroup::T_AND);
                continue;
            }
            elseif($value === Condition::LOGICAL_OP_AND)
            {
                continue;
            }

            if (is_string($key))
            {
                if (!is_array($value))
                {
                    throw new MalformedOptionException('"has" option "' . $key . '" is malformed.  Expected format: "\'' . $key . '\' => array(<conditions>)".');
                }

                $condition = $this->_has($key, $arborescence, $value);
                $andGroup->add($condition);
            }
            elseif (is_string($value))
            {
                $condition = $this->_has($value, $arborescence);
                $andGroup->add($condition);
            }
            else
            {
                throw new MalformedOptionException('A "has" option is malformed. Expected format: "<relation name> => array(<conditions>)" or "<relation name>".');
            }
        }

        // Add last values.
        if (! $andGroup->isEmpty())
        {
            $orGroup->add($andGroup);
        }

        return $orGroup;
    }
}
