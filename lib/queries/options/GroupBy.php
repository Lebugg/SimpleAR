<?php
namespace SimpleAR\Query\Option;

use \SimpleAR\Query\Option;
use \SimpleAR\Query\Arborescence;

class GroupBy extends Option
{
    public function build()
    {
        $aRes		= array();
		$sRootAlias = $this->_context->rootTable->alias;

        foreach ((array) $this->_value as $sAttribute)
        {
            $oAttribute = $this->_attribute($sAttribute);

			// Add related model(s) in join arborescence.
            $oNode           =
            $this->_arborescence->add($oAttribute->relations, Arborescence::JOIN_INNER, true);

            $oTableToGroupOn = $oNode->table;
            $sTableAlias     = $oTableToGroupOn->alias . ($oNode->depth ?: '');

            foreach ((array) $oTableToGroupOn->columnRealName($oAttribute->attribute) as $sColumn)
            {
                $this->_aGroupBy[] = '`' . $sTableAlias . '`.' .  $sColumn;
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
