<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

class OrderBy extends Option
{
	public function build()
	{
        $aRes		= array();
		$sRootAlias = $this->_context->rootTable->alias;

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge($this->_context->rootTable->orderBy, (array) $this->_value) as $sAttribute => $sOrder)
        {
            // Allows for a without-ASC/DESC syntax.
            if (is_int($sAttribute))
            {
                $sAttribute = $sOrder;
                $sOrder     = 'ASC';
            }

            if ($sAttribute == null)
            {
                throw new \SimpleAR\MalformedOptionException('"order_by" option malformed: attribute is empty.');
            }

            $oAttribute = $this->_attribute($sAttribute);

            if ($oAttribute->specialChar)
            {
                switch ($oAttribute->specialChar)
                {
                    case '#':
                        // Add related model(s) in join arborescence.
                        $oAttribute->relations[] = $oAttribute->attribute;
                        $oNode     =
                        $this->_arborescence->add($oAttribute->relations,
                        Arborescence::JOIN_LEFT, true);
                        $oRelation = $oNode->relation;

                        $oTableToGroupOn = $oRelation ? $oRelation->cm->t : $this->_context->rootTable;
                        $sResultAlias    = $oAttribute->lastRelation ?: $this->_context->rootResultAlias;

                        // We assure that keys are string because they might be arrays.
                        if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                        {
                            $sTableAlias = $oRelation->lm->alias;
                            $sKey		 = is_string($oRelation->lm->column) ?  $oRelation->lm->column : $oRelation->lm->column[0];
                        }
                        elseif ($oRelation instanceof \SimpleAR\ManyMany)
                        {
                            $sTableAlias = $oRelation->jm->alias;
                            $sKey		 = is_string($oRelation->jm->from) ? $oRelation->jm->from : $oRelation->jm->from[0];
                        }
                        else // BelongsTo
                        {
                            $sTableAlias = $oRelation->cm->alias;
                            $sKey		 = is_string($oRelation->cm->column) ?  $oRelation->cm->column : $oRelation->cm->column[0];

                            // We do not *need* to join this relation since we have a corresponding
                            // attribute in current model. Moreover, this is stupid to COUNT on a BelongsTo
                            // relationship since it would return 0 or 1.
                            $oNode->__force = false;
                        }

                        $sTableAlias .= ($oNode->depth ?: '');

                        // Count alias: `<result alias>.#<relation name>`;
                        $this->_aSelects[] = 'COUNT(`' . $sTableAlias . '`.' .  $sKey . ') AS `' .  $sResultAlias . '.#' . $oAttribute->attribute . '`';

                        // We need a GROUP BY.
                        if ($oTableToGroupOn->isSimplePrimaryKey)
                        {
                            $this->_aGroupBy[] = '`' . $sResultAlias . '.id`';
                        }
                        else
                        {
                            foreach ($oTableToGroupOn->primaryKey as $sPK)
                            {
                                $this->_aGroupBy[] = '`' . $sResultAlias . '.' . $sPK . '`';
                            }
                        }

                        $this->_aOrderBy[] = '`' . $sResultAlias . '.#' . $oAttribute->attribute .'`';
                        break;
                }
            }
            else
            {
                // Add related model(s) in join arborescence.
                $oNode     =
                $this->_arborescence->add($oAttribute->relations);
                $oRelation = $oNode->relation;

                $oCMTable = $oRelation ? $oRelation->cm->t : $this->_context->rootTable;
                $oLMTable = $oRelation ? $oRelation->lm->t : null;

                $iDepth = $oNode->depth ?: '';

                // ORDER BY is made on related model's attribute.
                if ($oNode->relation)
                {
                    // We *have to* include relation if we have to order on one of 
                    // its fields.
                    if ($oAttribute->attribute !== 'id')
                    {
                        $oNode->__force = true;
                    }

                    if ($oRelation instanceof \SimpleAR\HasMany || $oRelation instanceof \SimpleAR\HasOne)
                    {
                        $oNode->__force = true;

                        $sAlias  = $oLMTable->alias . $iDepth;
                        $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                    }
                    elseif ($oRelation instanceof \SimpleAR\ManyMany)
                    {
                        if ($sAttribute === 'id')
                        {
                            $sAlias  = $oRelation->jm->alias . $iDepth;
                            $mColumn = $oRelation->jm->to;
                        }
                        else
                        {
                            $sAlias  = $oLMTable->alias . $iDepth;
                            $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                        }
                    }
                    elseif ($oRelation instanceof \SimpleAR\BelongsTo)
                    {
                        if ($sAttribute === 'id')
                        {
                            $sAlias  = $oCMTable->alias . ($iDepth - 1 ?: '');
                            $mColumn = $oRelation->cm->column;
                        }
                        else
                        {
                            $sAlias  = $oLMTable->alias . $iDepth;
                            $mColumn = $oLMTable->columnRealName($oAttribute->attribute);
                        }
                    }
                }
                // ORDER BY is made on a current model's attribute.
                else
                {
                    $sAlias  = $oCMTable->alias;
                    $mColumn = $oCMTable->columnRealName($oAttribute->attribute);
                }

                foreach ((array) $mColumn as $sColumn)
                {
                    $aRes[] = $sAlias . '.' .  $sColumn . ' ' . $sOrder;
                }
			}
        }

        call_user_func($this->_callback, $aRes);
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
