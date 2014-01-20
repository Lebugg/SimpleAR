<?php
namespace SimpleAR\Query;

class Arborescence
{
    private $_oRoot;
    private $_sRootModel;

    const JOIN_INNER = 30;
    const JOIN_LEFT  = 20;
    const JOIN_RIGHT = 21;
    const JOIN_OUTER = 10;

    private $_aJoinTypes = array();

    private $_aJoinedTables = array();

    public function __construct($oRootTable, $sRootModel)
    {
        // Initialize arborescence: create root node.
        $this->_oRoot = $this->_node($oRootTable);

        // Initiliaze join type "int to string" translation array.
        $this->_aJoinTypes = array(
            self::JOIN_INNER => 'INNER',
            self::JOIN_LEFT  => 'LEFT',
            self::JOIN_RIGHT => 'RIGHT',
            self::JOIN_OUTER => 'OUTER',
        );

        $this->_sRootModel = $sRootModel;
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
    public function add($aRelationNames, $iJoinType = self::JOIN_INNER, $bForceJoin = false)
    {
        if (!$aRelationNames)
        {
            return $this->_oRoot;
        }

        $oNode         = $this->_oRoot;
        $sCurrentModel = $this->_sRootModel;

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
                $oNewNode = $this->_node($oRelation->lm->t, $oRelation, $oPreviousRelation, $i + 1, $iJoinType);

                $oNode = $oNode->next[$sRelation] = $oNewNode;
            }
            // Already present? We may have to change some values.
            else
            {
                $oNode = $oNode->next[$sRelation];
                $this->_node_updateJoinType($oNode, $iJoinType);
            }

            // Force join on last node if required.
            $oNode->force = $bForceJoin || $oNode->force;
        }

        // Return arborescence leaf.
        return $oNode;
    }

    public function process()
    {
        // Cross down the tree.
        return $this->_processRecursive($this->_oRoot, $this->_sRootModel);
    }

	private function _processRecursive($oNode, $sCurrentModel, $iDepth = 1)
	{
        $sRes = '';

        // Construct joined table array according to depth.
        if (! isset($this->_aJoinedTables[$iDepth]))
        {
            $this->_aJoinedTables[$iDepth] = array();
        }

		foreach ($oNode->next as $sRelation => $oNextNode)
		{
			$oRelation = $oNextNode->relation;
            $sTable    = $oRelation->lm->table;

            // We *have to* to join table if not already joined *and* it is not the last
            // relation.
            // __force bypasses the last relation condition. True typically when we have to join
            // this table for a "with" option.
            if (! in_array($sTable, $this->_aJoinedTables[$iDepth]) && ($oNextNode->next || $oNextNode->force) )
            {
                $sRes .= $oRelation->joinLinkedModel($iDepth, $this->_aJoinTypes[$oNextNode->joinType]);

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
                    if ($s = $oRelation->joinAsLast($oNextNode->conditions, $iDepth, $this->_aJoinTypes[$oNextNode->joinType]));
                    {
                        $sRes .= $s;

                        // Add it to joined tables array.
                        $this->_aJoinedTables[$iDepth][] = $sTable;
                    }
                }
            }

            // Go through arborescence.
            if ($oNextNode->next)
            {
				$sRes .= $this->_processRecursive($oNextNode, $oRelation->lm->class, $iDepth + 1);
            }

		}

        return $sRes;
	}

    private function _node(
        $oTable            = null,
        $oRelation         = null,
        $oPreviousRelation = null,
        $iDepth            = 0,
        $iJoinType         = self::JOIN_INNER
    ) {
        return (object) array(
            'table'            => $oTable,
            'relation'         => $oRelation,
            'previousRelation' => $oPreviousRelation,
            'conditions'       => array(),
            'depth'            => $iDepth,
            'joinType'         => $iJoinType,
            'force'            => false, // Should be removed.
            'next'             => array(),
        );
    }

    /**
     * Update the node join type.
     *
     * It always chooses the most restrictive JOIN when there is a conflict.
     */
    private function _node_updateJoinType($oNode, $iNewJoinType)
    {
        if ($oNode->joinType === $iNewJoinType)
        {
            return;
        }

        // If new join type is *stronger* (more restrictive) than old join type,
        // use new type.
        if ($oNode->joinType / 10 < $iNewJoinType / 10)
        {
            $oNode->joinType = $iNewJoinType;
        }

        // Special case for LEFT and RIGHT conflict. Use INNER.
        elseif ($oNode->joinType !== $iNewJoinType && $oNode->joinType / 10 === $iNewJoinType / 10)
        {
            $oNode->joinType = self::JOIN_INNER;
        }
    }

}
