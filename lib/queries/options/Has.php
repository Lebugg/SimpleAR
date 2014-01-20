<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Condition;
use \SimpleAR\Query\Condition\ExistsCondition;

class Has extends Option
{
    public function build()
    {
        $conditions = $this->_parse($this->_value);

        call_user_func($this->_callback, $conditions);
    }

    protected function _condition($oAttribute, array $aConditions = null)
    {
        $oNode      = $this->_arborescence->add($oAttribute->relations);
        $oCondition = new ExistsCondition($oAttribute->attribute, null, null);

        $oCondition->depth    = $oNode->depth;
        $oCondition->exists   = ! (bool) $oAttribute->specialChar;
        $oCondition->relation = $oNode->relation;

        $oNode->conditions[] = $oCondition;

        if ($aConditions)
        {
            $oCondition->subconditions = $this->_conditionsParse($aConditions);
        }

        return $oCondition;
    }


    public function _parse($aHas)
    {
        $aRes             = array();
        $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;

        foreach ($aHas as $mKey => $mValue)
        {
            // It is a logical operator.
            if     ($mValue === 'OR'  || $mValue === '||') { $sLogicalOperator = 'OR';  }
            elseif ($mValue === 'AND' || $mValue === '&&') { $sLogicalOperator = 'AND'; }

            if (is_string($mKey))
            {
                if (!is_array($mValue))
                {
                    throw new \SimpleAR\MalformedOptionException('"has" option "' . $mKey . '" is malformed.  Expected format: "\'' . $mKey . '\' => array(<conditions>)".');
                }

                $oCondition = $this->_condition($this->_attribute($mKey, true), $mValue);
            }
            elseif (is_string($mValue))
            {
                $oCondition = $this->_condition($this->_attribute($mValue, true));
                $aRes[]     = array($sLogicalOperator, $oCondition);
            }
            else
            {
                throw new \SimpleAR\MalformedOptionException('A "has" option is malformed. Expected format: "<relation name> => array(<conditions>)" or "<relation name>".');
            }

            // Reset operator.
            $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;
        }

        return $aRes;
    }

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
