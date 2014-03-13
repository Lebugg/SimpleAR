<?php namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Option\Conditions;

use \SimpleAR\Query\Condition as Condition;
use \SimpleAR\Query\Arborescence;
use \SimpleAR\Exception\MalformedOption;


class Has extends Conditions
{
    protected static $_name = 'has';

    public function build($useModel, $model = null)
    {
        $this->where = $this->_parseHas($this->_value);
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
    protected function _has($attribute, array $conditions = null)
    {
        $relations = explode('/', $attribute);
        $last      = array_pop($relations);

        $not = false;
        if ($last[0] === self::SYMBOL_NOT)
        {
            $not  = true;
            $last = substr($last, 1);
        }

        $relations[] = $last;

        $has = new Condition\Exists();
        $has->not       = $not;
        $has->relations = $relations;
        
        if ($conditions)
        {
            $has->subconditions = $this->_parse($conditions);
        }

        $this->joins[] = $relations;

        return $has;
    }


    public function _parseHas($has)
    {
        $root = $currentAndGroup = new Condition\Group(Condition\Group::T_AND);

        foreach ($has as $key => $value)
        {
            // It is a logical operator.
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
            elseif($value === Condition::LOGICAL_OP_AND)
            {
                continue;
            }

            if (is_string($key))
            {
                if (! is_array($value))
                {
                    throw new MalformedOption('"has" option "' . $key . '" is malformed.  Expected format: "\'' . $key . '\' => array(<conditions>)".');
                }

                $condition = $this->_has($key);
                $currentAndGroup->add($condition);
            }
            elseif (is_string($value))
            {
                $condition = $this->_has($value);
                $currentAndGroup->add($condition);
            }
            else
            {
                throw new MalformedOption('A "has" option is malformed. Expected format: "relation name => array(conditions)" or "relation name".');
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
