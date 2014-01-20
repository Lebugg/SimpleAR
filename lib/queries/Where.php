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
	protected $_oArborescence;
	protected $_aConditions = array();

    protected $_sJoin         = '';

    protected $_aJoinedTables = array();

    const JOIN_INNER = 30;
    const JOIN_LEFT  = 20;
    const JOIN_RIGHT = 21;
    const JOIN_OUTER = 10;

    protected $_aJoinTypes = array(
        30 => 'INNER',
        20 => 'LEFT',
        21 => 'RIGHT',
        10 => 'OUTER',
    );

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
     * Add a relation array into the join arborescence of the query.
     *
     * @param array $aRelationNames Array containing relations ordered by depth to add to join
     * arborescence.
     * @param int $iJoinType The type of join to use to join this relation. Default: inner join.
     * @param bool $bForceJoin Does relation *must* be joined?
     *
     * To be used as:
     *  ```php
     *  $a = $this->_addToArborescence(...);
     *  $aArborescence = &$a[0];
     *  $oRelation     = $a[1];
     *  ```
     * @return array
     */
    protected function _addToArborescence($aRelationNames, $iJoinType = self::JOIN_INNER, $bForceJoin = false)
    {
        if (!$aRelationNames)
        {
            return $this->_oContext->arborescence;
        }

        $oNode         = $this->_oContext->arborescence;
        $sCurrentModel = $this->_oContext->rootModel;

        $oRelation         = null;
        $oPreviousRelation = null;

        // Foreach relation to add, set some "config" elements.
        foreach ($aRelationNames as $i => $sRelation)
        {
            $oPreviousRelation = $oRelation;
            $oRelation         = $sCurrentModel::relation($sRelation);
            $sCurrentModel     = $oRelation->lm->class;

            // Add a new node if needed.
            if (! isset($oNode->next[$sRelation]))
            {
                $oNode = $oNode->next[$sRelation] = new \StdClass();

                $oNode->__type       = $iJoinType;
                $oNode->conditions = array();
                $oNode->__force      = false;
                $oNode->next  = array();

                $oNode->previousRelation = $oPreviousRelation;
                $oNode->relation = $oRelation;
                $oNode->depth    = $i + 1;
                $oNode->table    = $oRelation->lm->t;
            }
            // Already present? We may have to change some values.
            else
            {
                $oNode = $oNode->next[$sRelation];

                // Choose right join type.
                $iCurrentType = $oNode->__type;

                // If new join type is *stronger* (more restrictive) than old join type, use new
                // type.
                if ($iCurrentType / 10 < $iJoinType / 10)
                {
                    $oNode->__type = $iJoinType;
                }
                // Special case for LEFT and RIGHT conflict. Use INNER.
                elseif ($iCurrentType !== $iJoinType && $iCurrentType / 10 === $iJoinType / 10)
                {
                    $oNode->__type = self::JOIN_INNER;
                }
            }

            // Force join on last node if required.
            $oNode->__force = $bForceJoin || $oNode->__force;
        }

        // Return arborescence leaf.
        var_dump($oNode->table);
        return $oNode;
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

        $oNode = $this->_addToArborescence($oAttribute->pieces);
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
        $oNode      = $this->_addToArborescence($oAttribute->pieces);
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

        $o = new \StdClass();
        $o->next             = array();
        $o->conditions       = array();
        $o->relation         = null;
        $o->previousRelation = null;
        $o->depth            = 0;
        $o->table            = $this->_oContext->rootTable;

        $this->_oContext->arborescence = $o;
    }

    protected function _processArborescence()
    {
        // We cannot use arborescence feature when we are not using models.
        // So, abort it.
        if (! $this->_oContext->useModel)
        {
            return false;
        }

        // Process root node here.
        $oRoot = $this->_oContext->arborescence;
        // Nothing to do?

        // Cross down the tree.
        $this->_processArborescenceRecursive($oRoot, $this->_oContext->rootModel);
    }

	private function _processArborescenceRecursive($oArborescence, $sCurrentModel, $iDepth = 1)
	{
        // Construct joined table array according to depth.
        if (! isset($this->_aJoinedTables[$iDepth]))
        {
            $this->_aJoinedTables[$iDepth] = array();
        }

		foreach ($oArborescence->next as $sRelation => $oNextNode)
		{
			$oRelation = $oNextNode->relation;
            $sTable    = $oRelation->lm->table;

            // We *have to* to join table if not already joined *and* there it is not the last
            // relation.
            // __force bypasses the last relation condition. True typically when we have to join
            // this table for a "with" option.
            if (! in_array($sTable, $this->_aJoinedTables[$iDepth]) && ($oNextNode->next || $oNextNode->__force) )
            {
                $this->_sJoin .= $oRelation->joinLinkedModel($iDepth, $this->_aJoinTypes[$oNextNode->__type]);

                // Add it to joined tables array.
                $this->_aJoinedTables[$iDepth][] = $sTable;
            }

            // We have stuff to do with conditions:
            // 1. Join the relation as last if not already joined (to be able to apply potential
            // conditions).
            if ($oNextNode->conditions)
            {
                // If not already joined, do it as last relation.
                if (! in_array($sTable, $this->_aJoinedTables[$iDepth]))
                {
                    // $s is false if joining table was not necessary.
                    if ($s = $oRelation->joinAsLast($oNextNode->conditions, $iDepth, $this->_aJoinTypes[$oNextNode->__type]));
                    {
                        $this->_sJoin .= $s;

                        // Add it to joined tables array.
                        $this->_aJoinedTables[$iDepth][] = $sTable;
                    }
                }
            }

            // Go through arborescence.
            if ($oNextNode->next)
            {
				$this->_sJoin .= $this->_processArborescenceRecursive($oNextNode, $oRelation->lm->class, $iDepth + 1);
            }

		}
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
