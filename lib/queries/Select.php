<?php
/**
 * This file contains the Select class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

/**
 * This class handles SELECT statements.
 */
class Select extends \SimpleAR\Query\Where
{
    /**
     * Contains all attributes to fetch.
     *
     * @var array
     */
	private $_aSelects		= array();

    /**
     * Contains ORDER BY clause.
     *
     * @var string
     */
	private $_sOrderBy;

    /**
     * Contains attributes to GROUP BY on.
     *
     * @var array
     */
	private $_aGroupBy		= array();

    private $_aPendingRow = false;

    /**
     * Return all fetch Model instances.
     *
     * This function is just a foreach wrapper around `row()`.
     *
     * @see Select::row()
     *
     * @return array
     */
    public function all()
    {
        $aRes = array();

        while ($aOne = $this->row())
        {
            $aRes[] = $aOne;
        }

        return $aRes;
    }

    /**
     * Return first fetch Model instance.
     *
     * @return Model
     */
    public function row()
    {
        $aRes = array();

        $aReversedPK = $this->_oRootTable->isSimplePrimaryKey ? array('id' => 0) : array_flip((array) $this->_oRootTable->primaryKey);
        $aResId      = null;

        // We want one resulting object. But we may have to process several lines in case that eager
        // load of related models have been made with has_many or many_many relations.
        while (
               ($aRow = $this->_aPendingRow)                    !== false ||
               ($aRow = $this->_oSth->fetch(\PDO::FETCH_ASSOC)) !== false
        ) {

            // Prevent infinite loop.
            if ($this->_aPendingRow) { $this->_aPendingRow = false; }

            $aParsedRow = $this->_parseRow($aRow);

            // New main object, we are finished.
            if ($aRes && $aResId !== array_intersect_key($aParsedRow, $aReversedPK)) // Compare IDs
            {
                $this->_aPendingRow = $aRow;
                break;
            }

            // Same row but there is no linked model to fetch. Weird. Query must be not well
            // constructed. (Lack of GROUP BY).
            if ($aRes && !isset($aParsedRow['_WITH_']))
            {
                continue;
            }

            // Now, we have to combined new parsed row with our constructing result.

            if ($aRes)
            {
                // Merge related models.
                $aRes = array_merge_recursive_distinct($aRes, $aParsedRow);
            }
            else
            {
                $aRes   = $aParsedRow;

                // Store result object ID for later use.
                $aResId = array_intersect_key($aRes, $aReversedPK);
            }
        }

        return $aRes;
    }

	private function _analyzeGroupBy($aGroupBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        foreach ($aGroupBy as $sAttribute)
        {
            $aPieces = explode('/', $sAttribute);

			// One or several attributes of a related model.
            $aAttributes = explode(',', array_pop($aPieces));

			// Add related model(s) in join arborescence.
            $a = $this->_addToArborescence($aPieces);
            $aArborescence =& $a[0];
            $oRelation     =  $a[1];

            $sResultAlias    = $oRelation ? $oRelation->name  : '_';
            $oTableToGroupOn = $oRelation ? $oRelation->lm->t : $this->_oRootTable;

            foreach ((array) $aAttributes as $sAttribute)
            {
                if (! $oTableToGroupOn->hasAttribute($sAttribute))
                {
                    throw new Exception('Attribute "' . $sAttribute . '" does not exist for model "' . $oTableToGroupOn->modelBaseName . '" in group_by option.');
                }

                $this->_aGroupBy[] = '`' . $sResultAlias . '.' .  $sAttribute . '`';
            }
        }
	}

	private function _analyzeOrderBy($aOrderBy)
	{
        $aRes		= array();
		$sRootAlias = $this->_oRootTable->alias;

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge($this->_oRootTable->orderBy, $aOrderBy) as $sAttribute => $sOrder)
        {
            // Allows for without ASC/DESC syntax.
            if (is_int($sAttribute))
            {
                $sAttribute = $sOrder;
                $sOrder     = 'ASC';
            }

            $aPieces = explode('/', $sAttribute);

			// Attribute of a related model.
			$sAttribute = array_pop($aPieces);

            // Result alias will be the last-but-one relation name.
            if ($aPieces)
            {
                $sResultAlias = end($aPieces); reset($aPieces);
            }
            // Or the root model symbol.
            else
            {
                $sResultAlias = '_';
            }


            // If the order is made on a relation count, re-push the relation name in the relation
            // array.
			if ($sAttribute[0] === '#')
            {
                // Without "#".
                array_push($aPieces, substr($sAttribute, 1));
            }

			// Add related model(s) in join arborescence.
            $a = $this->_addToArborescence($aPieces);
            $aArborescence =& $a[0];
            $oRelation     =  $a[1];

			if ($sAttribute[0] === '#')
			{
                $oTableToGroupOn = $oRelation ? $oRelation->cm->t : $this->_sRootModel;

				$aRes[] = $this->_orderByCount($oRelation, $sOrder, $oTableToGroupOn, $sResultAlias, $aArborescence);
			}
            else
            {
                $oCurrentTable = $oRelation ? $oRelation->lm->t : $this->_oRootTable;

                $aRes[] = $oCurrentTable->alias . '.' .  $oCurrentTable->columnRealName($sAttribute) . ' ' . $sOrder;
            }
        }

        $this->_sOrderBy = $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
	}

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	public function _build($aOptions)
	{
		$sRootModel = $this->_sRootModel;
		$sRootAlias = $this->_oRootTable->alias;

		$this->_aSelects = (isset($aOptions['filter']))
			? $sRootModel::columnsToSelect($aOptions['filter'], $sRootAlias, '_')
			: $sRootModel::columnsToSelect(null, $sRootAlias, '_')
			;

		if (isset($aOptions['conditions']))
		{
            $this->_where($aOptions['conditions']);
		}

		if (isset($aOptions['order_by']))
		{
			$this->_analyzeOrderBy($aOptions['order_by']);
		}

        if (isset($aOptions['with']))
        {
			$this->_with($aOptions['with']);
        }

        $this->_arborescenceToSql();

		$this->sql  = 'SELECT ' . implode(', ', $this->_aSelects);
		$this->sql .= ' FROM ' . $this->_oRootTable->name . ' ' . $sRootAlias .  ' ' . $this->_sJoin;
		$this->sql .= $this->_sWhere;
		$this->sql .= $this->_groupBy();
		$this->sql .= $this->_sOrderBy;

		if (isset($aOptions['limit']))
		{
			$this->sql .= ' LIMIT ' . $aOptions['limit'];
		}

		if (isset($aOptions['offset']))
		{
			$this->sql .= ' OFFSET ' . $aOptions['offset'];
		}
	}


	private function _groupBy()
	{
		return $this->_aGroupBy ? ' GROUP BY ' . implode(',', $this->_aGroupBy) : '';
	}

	private function _orderByCount($oRelation, $sOrder, $oTableToGroupOn, $sResultAlias, &$aArborescence)
	{
        // $sCountAlias: `<result alias>.#<relation name>`;
		$sCountAlias = '`' . $sResultAlias . '.#' . $oRelation->name . '`';

        $aArborescence['_TYPE_'] = self::JOIN_INNER;

		if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
		{
			$sTableAlias = $oRelation->lm->alias;
			$sKey		 = $oRelation->lm->column;

            $aArborescence['_FORCE_'] = true;
		}
		elseif ($oRelation instanceof \SimpleAR\ManyMany)
		{
			$sTableAlias = $oRelation->jm->alias;
			$sKey		 = $oRelation->jm->from;

            $aArborescence['_FORCE_'] = true;
		}
		else // BelongsTo
		{
			$sTableAlias = $oRelation->cm->alias;
			$sKey		 = $oRelation->cm->column;
		}

		$this->_aSelects[] = 'COUNT(' . $sTableAlias . '.' .  $sKey . ') AS ' . $sCountAlias;
		$this->_aGroupBy[] = $oTableToGroupOn->alias . '.' . $oTableToGroupOn->primaryKey;

		return $sCountAlias . ' ' . $sOrder;
	}

    private function _parseRow($aRow)
    {
        $aRes = array();

        foreach ($aRow as $sKey => $mValue)
        {
            $a = explode('.', $sKey);

            if ($a[0] === '_')
            {
                // $a[1]: table column name.

                $aRes[$a[1]] = $mValue;
            }
            else
            {
                // $a[0]: relation name.
                // $a[1]: related table column name.

                $aRes['_WITH_'][$a[0]][$a[1]] = $mValue;
            }
        }

        return $aRes;
    }

    private function _with($m)
    {
        $a = (array) $m;

        foreach($a as $sRelation)
        {
            list($a, $oRelation) = $this->_addToArborescence(explode('/', $sRelation), self::JOIN_LEFT, true);

            $sLM = $oRelation->lm->class;
            $this->_aSelects = array_merge(
                $this->_aSelects,
                $sLM::columnsToSelect($oRelation->filter, $oRelation->lm->alias, $sRelation)
            );
        }
    }

}
