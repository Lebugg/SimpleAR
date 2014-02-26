<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Option\Conditions;

use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\Attribute;
use \SimpleAR\Query\Condition\ConditionGroup;
use \SimpleAR\Query\Condition\ExistsCondition;

use \SimpleAR\Query\Arborescence;

use \SimpleAR\Exception\MalformedOption;


class Has extends Conditions
{
    public function build()
    {
        $this->conditions = $this->_parseHas($this->_value, $this->_arborescence);
    }

    /**
     * Handle a "has condition".
     *
     * @param stdClass $attribute An attribute object returned by
     * Attribute::parse().
     * @param array    $conditions Optional conditions to add to the "has
     * condition".
     *
     * @return ExistsCondition
     */
    protected function _has($attribute, Arborescence $arborescence, array $conditions = null)
    {
        $attribute = Attribute::parse($attribute, true);

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
        $root = $currentAndGroup = new ConditionGroup(ConditionGroup::T_AND);

        foreach ($has as $key => $value)
        {
            // It is a logical operator.
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
                    $root->add($currentAndGroup);
                }

                // And initialize a new one.
                $currentAndGroup = new ConditionGroup(ConditionGroup::T_AND);
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
                    throw new MalformedOption('"has" option "' . $key . '" is malformed.  Expected format: "\'' . $key . '\' => array(<conditions>)".');
                }

                $condition = $this->_has($key, $arborescence, $value);
                $currentAndGroup->add($condition);
            }
            elseif (is_string($value))
            {
                $condition = $this->_has($value, $arborescence);
                $currentAndGroup->add($condition);
            }
            else
            {
                throw new MalformedOption('A "has" option is malformed. Expected format: "<relation name> => array(<conditions>)" or "<relation name>".');
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
