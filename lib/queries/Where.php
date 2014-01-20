<?php /**
 * This file contains the Where class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

use SimpleAR\Query\Condition\ExistsCondition;
use SimpleAR\Query\Condition\RelationCondition;
use SimpleAR\Query\Condition\SimpleCondition;

/**
 * This class is the super classe for queries that handle conditions (WHERE statements).
 */
abstract class Where extends \SimpleAR\Query
{
	protected $_oArborescence;
	protected $_aConditions = array();

    public function conditions($aConditions)
    {
        $this->_aConditions = $this->_conditionsParse($aConditions);
    }
    
    /**
     * EXISTS conditions.
     */
    public function has($aHas)
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

                $oCondition = $this->_conditionExists($this->_attribute($mKey, true), $mValue);
            }
            elseif (is_string($mValue))
            {
                $oCondition = $this->_conditionExists($this->_attribute($mValue, true));
                $this->_aConditions[]     = array($sLogicalOperator, $oCondition);
            }
            else
            {
                throw new \SimpleAR\MalformedOptionException('A "has" option is malformed. Expected format: "<relation name> => array(<conditions>)" or "<relation name>".');
            }

            // Reset operator.
            $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;
        }
    }

    /**
     * Returns an attribute object.
     */
    protected function _attribute($sAttribute, $bOnlyRelation = false)
    {
        $sOriginal = $sAttribute;

        $aPieces = explode('/', $sAttribute);

        $sAttribute    = array_pop($aPieces);
        $sLastRelation = array_pop($aPieces);

        if ($sLastRelation)
        {
            $aPieces[] = $sLastRelation;
        }

        $aAttributes = explode(',', $sAttribute);
        if (isset($aAttributes[1]))
        {
            $mAttribute = $aAttributes;
        }
        else
        {
            $mAttribute = $sAttribute;
            // (ctype_alpha tests if charachter is alphabetical ([a-z][A-Z]).)
            $cSpecial   = ctype_alpha($sAttribute[0]) ? null : $sAttribute[0];

            if ($cSpecial)
            {
                $mAttribute = substr($mAttribute, 1);
            }
        }

        if ($bOnlyRelation)
        {
            if (is_array($mAttribute))
            {
                throw new \SimpleAR\Exception('Cannot have multiple attributes in “' . $sAttribute . '”.');
            }

            $aPieces[] = $mAttribute;
        }

        return (object) array(
            'pieces'       => $aPieces,
            'lastRelation' => $sLastRelation,
            'attribute'    => $mAttribute,
            'specialChar'  => $cSpecial,
            'original'     => $sOriginal,
        );
    }

    protected function _condition($oAttribute, $sOperator, $mValue)
    {
        // Special attributes check.
        if ($c = $oAttribute->specialChar)
        {
            switch ($c)
            {
                case '#':
                    $this->_having($oAttribute, $sOperator, $mValue);
                    return;
                default:
                    throw new \SimpleAR\Exception('Unknown symbole “' . $c . '” in attribute “' . $sAttribute . '”.');
                    break;
            }
        }

        $oNode = $this->_oContext->arborescence->add($oAttribute->pieces);
        $mAttr = $oAttribute->attribute;

        if ($oNode->relation)
        {
            $oCondition = new RelationCondition($mAttr, $sOperator, $mValue);
            $oCondition->relation = $oNode->relation;
        }
        else
        {
            $oCondition = new SimpleCondition($mAttr, $sOperator, $mValue);
        }
        $oCondition->depth = $oNode->depth;
        $oCondition->table = $oNode->table;

        // Is there a Model's method to handle this attribute? Useful for virtual attributes.
        if ($this->_oContext->useModel && is_string($mAttr))
        {
            $sModel  = $oNode->relation ? $oNode->relation->lm->class : $this->_oContext->rootModel;
            $sMethod = 'to_conditions_' . $mAttr;

            if (method_exists($sModel, $sMethod))
            {
                if ($a = $sModel::$sMethod($oCondition))
                {
                    $oCondition->virtual = true;
                    $oCondition->subconditions = $this->_conditionsParse($a);
                }
            }
        }

        $oNode->conditions[] = $oCondition;

        return $oCondition;
    }

    protected function _conditionExists($oAttribute, array $aConditions = null)
    {
        $oNode      = $this->_oContext->arborescence->add($oAttribute->pieces);
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

    protected function _conditionsParse($aConditions)
    {
        $aRes = array();

        $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;
        $oCondition       = null;

        foreach ($aConditions as $mKey => $mValue)
        {
            // It is bound to be a condition. 'myAttribute' => 'myValue'
            if (is_string($mKey))
            {
                $oCondition = $this->_condition($this->_attribute($mKey), null, $mValue);
                if ($oCondition)
                {
                    $aRes[]     = array($sLogicalOperator, $oCondition);
                }

                // Reset operator.
                $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;
            }

            // It can be a condition, a condition group, or a logical operator.
            else
            {
                // It is a logical operator.
                if     ($mValue === 'OR'  || $mValue === '||') { $sLogicalOperator = 'OR';  }
                elseif ($mValue === 'AND' || $mValue === '&&') { $sLogicalOperator = 'AND'; }

                // Condition or condition group.
                else
                {
                    // Condition.
                    if (isset($mValue[0]) && is_string($mValue[0]))
                    {
                        $oCondition = $this->_condition($this->_attribute($mValue[0]), $mValue[1], $mValue[2]);
                        if ($oCondition)
                        {
                            $aRes[]     = array($sLogicalOperator, $oCondition);
                        }
                    }

                    // Condition group.
                    else
                    {
                        $aRes[] = array($sLogicalOperator, $this->_conditionsParse($mValue));
                    }

                    // Reset operator.
                    $sLogicalOperator = Condition::DEFAULT_LOGICAL_OP;
                }
            }

            $oCondition = null;
        }

        return $aRes;
    }

    protected function _having($oAttribute, $sOperator, $mValue)
    {
        throw new \SimpleAR\Exception('Cannot add this condition "' . $oAttribute->original . '" in a ' .  get_class($this) . ' query.');
    }

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        $this->_oContext->arborescence = new Arborescence(
            $this->_oContext->rootTable,
            $this->_oContext->rootModel
        );
    }

    protected function _processArborescence()
    {
        // We cannot use arborescence feature when we are not using models.
        // So, abort it.
        if (! $this->_oContext->useModel)
        {
            return '';
        }

        return $this->_oContext->arborescence->process();
    }

    protected function _where()
    {
        // We made all wanted treatments; get SQL out of Condition array.
        list($sSql, $aValues) = Condition::arrayToSql($this->_aConditions, $this->_oContext->useAlias, $this->_oContext->useModel);

        // Add condition values. $aValues is a flatten array.
        // @see Condition::flattenValues()
        $this->_aValues = array_merge($this->_aValues, $aValues);

		return $sSql ? ' WHERE ' . $sSql : '';
    }

}
