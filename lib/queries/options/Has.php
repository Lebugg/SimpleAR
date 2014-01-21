<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\ExistsCondition;

use \SimpleAR\MalformedOptionException;

class Has extends Option
{
    public function build()
    {
        $conditions = $this->_parse($this->_value);

        call_user_func($this->_callback, $conditions);
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
    protected function _condition($attribute, array $conditions = null)
    {
        $node      = $this->_arborescence->add($attribute->relations);
        $condition = new ExistsCondition($attribute->attribute, null, null);

        $condition->depth    = $node->depth;
        $condition->exists   = ! (bool) $attribute->specialChar;
        $condition->relation = $node->relation;

        $node->conditions[] = $condition;

        if ($conditions)
        {
            $condition->subconditions = $this->_conditionsParse($conditions);
        }

        return $condition;
    }


    public function _parse($has)
    {
        $res             = array();
        $logicalOperator = Condition::DEFAULT_LOGICAL_OP;

        foreach ($has as $key => $value)
        {
            // It is a logical operator.
            if     ($value === 'OR'  || $value === '||') { $logicalOperator = 'OR';  }
            elseif ($value === 'AND' || $value === '&&') { $logicalOperator = 'AND'; }

            if (is_string($key))
            {
                if (!is_array($value))
                {
                    throw new MalformedOptionException('"has" option "' . $key . '" is malformed.  Expected format: "\'' . $key . '\' => array(<conditions>)".');
                }

                $condition = $this->_condition(self::_parseAttribute($key, true), $value);
            }
            elseif (is_string($value))
            {
                $condition = $this->_condition(self::_parseAttribute($value, true));
                $res[]     = array($logicalOperator, $condition);
            }
            else
            {
                throw new MalformedOptionException('A "has" option is malformed. Expected format: "<relation name> => array(<conditions>)" or "<relation name>".');
            }

            // Reset operator.
            $logicalOperator = Condition::DEFAULT_LOGICAL_OP;
        }

        return $res;
    }
}
