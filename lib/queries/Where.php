<?php
/**
 * This file contains the Where class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class is the super classe for queries that handle conditions (WHERE statements).
 */
abstract class Where extends \SimpleAR\Query
{
	protected $_oArborescence;
	protected $_aConditions = array();

    protected $_sJoin         = '';
    protected $_sWhere        = '';

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

    public function __construct($sRoot)
    {
        parent::__construct($sRoot);

        $this->_oArborescence = new \StdClass();
        $this->_oArborescence->__relations  = array();
        $this->_oArborescence->__conditions = array();
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
        if (!$aRelationNames) { return array(&$this->_oArborescence, null); }

        // Add related model(s) in join arborescence.
        $oNode         = $this->_oArborescence;
        $sCurrentModel = $this->_sRootModel;
        $oRelation     = null;

        // Foreach relation to add, set some "config" elements.
        foreach ($aRelationNames as $sRelation)
        {
            // Add a new node if needed.
            if (! isset($oNode->__relations[$sRelation]))
            {
                $oNode = $oNode->__relations[$sRelation] = new \StdClass();

                $oNode->__type       = $iJoinType;
                $oNode->__conditions = array();
                $oNode->__force      = false;
                $oNode->__relations  = array();
            }
            // Already present? We may have to change some values.
            else
            {
                $oNode = $oNode->__relations[$sRelation];

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

            $oRelation     = $sCurrentModel::relation($sRelation);
            $sCurrentModel = $oRelation->lm->class;
        }

        // Return arborescence leaf and current Relation object.
        return array($oNode, $oRelation);
    }

    protected function _conditions($aConditions)
    {
        $this->_aConditions = $this->_conditionsParse($aConditions);
    }
    
    protected function _conditionsParse($aConditions)
    {
        $aRes = array();

        $sLogicalOperator = \SimpleAR\Condition::DEFAULT_LOGICAL_OP;
        $oCondition       = null;

        foreach ($aConditions as $mKey => $mValue)
        {
            // It is bound to be a condition. 'myAttribute' => 'myValue'
            if (is_string($mKey))
            {
                $oCondition = new \SimpleAR\Condition($mKey, null, $mValue);
                $aRes[]     = array($sLogicalOperator, $oCondition);

                // Reset operator.
                $sLogicalOperator = \SimpleAR\Condition::DEFAULT_LOGICAL_OP;
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
                        $oCondition = new \SimpleAR\Condition($mValue[0], $mValue[1], $mValue[2]);
                        $aRes[]     = array($sLogicalOperator, $oCondition);
                    }

                    // Condition group.
                    else
                    {
                        $aRes[] = array($sLogicalOperator, $this->_conditionsParse($mValue));
                    }

                    // Reset operator.
                    $sLogicalOperator = \SimpleAR\Condition::DEFAULT_LOGICAL_OP;
                }
            }

            // Condition object may contains an arborescence string as an attribute.
            if ($oCondition && $this->_bUseModel)
            {
                $this->_conditionsExtractArborescence($oCondition);
            }

            $oCondition = null;
        }

        return $aRes;
    }

	private function _conditionsExtractArborescence($oCondition)
	{
        $sAttribute = $oCondition->attribute;
        $sOperator  = $oCondition->operator;
        $mValue     = $oCondition->value;

        // We want to save arborescence without attribute name for later use.
        $sArborescence = ($i = strrpos($sAttribute, '/')) ? substr($sAttribute, 0, $i + 1) : '';

        $aRelationPieces = explode('/', $sAttribute);
        $sAttribute      = array_pop($aRelationPieces);

        // Add related model(s) into join arborescence.
        list($oNode, $oRelation) = $this->_addToArborescence($aRelationPieces);

        $sCurrentModel = $oRelation ? $oRelation->lm->class : $this->_sRootModel;

        // Update Condition data.
        $oCondition->relation  = $oRelation;
        $oCondition->table     = $oRelation ? $oRelation->lm->t : $this->_oRootTable;
        $oCondition->attribute = $sAttribute;

        // Is there a method to handle this attribute? Useful for *virtual* attributes.
        $sToConditionsMethod = 'to_conditions_' . $sAttribute;
        if (method_exists($sCurrentModel, $sToConditionsMethod))
        {
            $aSubConditions = $sCurrentModel::$sToConditionsMethod($oCondition, $sArborescence);

             // to_conditions_* may return nothing when they directly modify
             // the Condition object.
            if ($aSubConditions)
            {
                $oCondition->virtual = true;
                $aSubConditions      = $this->_conditionsParse($aSubConditions);
                /*
                foreach($aSubConditions as $oSubCondition)
                {
                    $oSubCondition->table    = $oCondition->table;
                    $oSubCondition->relation = $oCondition->relation;
                }
                */
                $oCondition->subconditions = $aSubConditions;
            }
        }
        else
        {
            $oNode->__conditions[] = $oCondition;
        }
	}

    protected function _processArborescence()
    {
        // Process root node here.
        $oRoot = $this->_oArborescence;
        // Nothing to do?

        // Cross down the tree.
        $this->_processArborescenceRecursive($oRoot, $this->_sRootModel);
    }

	private function _processArborescenceRecursive($oArborescence, $sCurrentModel, $iDepth = 1)
	{
        // Construct joined table array according to depth.
        if (! isset($this->_aJoinedTables[$iDepth]))
        {
            $this->_aJoinedTables[$iDepth] = array();
        }

		foreach ($oArborescence->__relations as $sRelation => $oNextNode)
		{
			$oRelation = $sCurrentModel::relation($sRelation);
            $sTable    = $oRelation->lm->table;

            // We *have to* to join table if not already joined *and* there it is not the last
            // relation.
            // __force bypasses the last relation condition. True typically when we have to join
            // this table for a "with" option.
            if (! in_array($sTable, $this->_aJoinedTables[$iDepth]) && ($oNextNode->__relations || $oNextNode->__force) )
            {
                $this->_sJoin .= $oRelation->joinLinkedModel($iDepth, $this->_aJoinTypes[$oNextNode->__type]);

                // Add it to joined tables array.
                $this->_aJoinedTables[$iDepth][] = $sTable;
            }

            // If there is some other relations to join, join them.
            if ($oNextNode->__relations)
            {
				$this->_sJoin .= $this->_processArborescenceRecursive($oNextNode, $oRelation->lm->class, $iDepth + 1);
            }

            
            // We have stuff to do with conditions:
            // 1. Tell them their depth.
            // 2. Join the relation as last if not already joined (to be able to apply potential
            // conditions).
            if ($oNextNode->__conditions)
            {
                // Tell conditions their depth because they did not knwow it until now.
                foreach ($oNextNode->__conditions as $oCondition)
                {
                    $oCondition->depth = $iDepth;
                }

                // If not already joined, do it as last relation.
                if (! in_array($sTable, $this->_aJoinedTables[$iDepth]))
                {
                    // $s is false if joining table was not necessary.
                    if ($s = $oRelation->joinAsLast($oNextNode->__conditions, $iDepth, $this->_aJoinTypes[$oNextNode->__type]));
                    {
                        $this->_sJoin .= $s;

                        // Add it to joined tables array.
                        $this->_aJoinedTables[$iDepth][] = $sTable;
                    }
                }
            }
		}
	}

    protected function _where()
    {
        // We made all wanted treatments; get SQL out of Condition array.
        // We update values because Condition::arrayToSql() will flatten them in
        // order to bind them to SQL string with PDO.
        list($sSql, $this->values) = \SimpleAR\Condition::arrayToSql($this->_aConditions, $this->_bUseAlias, $this->_bUseModel);

		return $this->_sWhere = ($sSql ? ' WHERE ' . $sSql : '');
    }

}
