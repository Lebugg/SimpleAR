<?php
/**
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
	protected $_aConditions = array();

    public function conditions($conditions)
    {
        $this->_aConditions = $conditions;
    }
    
    /**
     * EXISTS conditions.
     */
    public function has($a)
    {
        $this->_aConditions = array_merge($this->_aConditions, $a);
    }

    /*
    protected function _conditionExists($oAttribute, array $aConditions = null)
    {
        $oNode      = $this->_context->arborescence->add($oAttribute->pieces);
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
    */

    /*
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
    */

    protected function _initContext($sRoot)
    {
        parent::_initContext($sRoot);

        $this->_context->arborescence = new Arborescence(
            $this->_context->rootTable,
            $this->_context->rootModel
        );
    }

    protected function _processArborescence()
    {
        return $this->_context->arborescence->process();
    }


    protected function _where()
    {
        // We made all wanted treatments; get SQL out of Condition array.
        list($sSql, $aValues) = Condition::arrayToSql($this->_aConditions, $this->_context->useAlias, $this->_context->useModel);

        // Add condition values. $aValues is a flatten array.
        // @see Condition::flattenValues()
        $this->_aValues = array_merge($this->_aValues, $aValues);

		return $sSql ? ' WHERE ' . $sSql : '';
    }

}
