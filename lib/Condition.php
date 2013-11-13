<?php
namespace SimpleAR;

class Condition
{
    protected static $_aArrayOperators = array(
        '='  => 'IN',
        '!=' => 'NOT IN',
        '<'  => '< ANY',
        '>'  => '> ANY',
        '<=' => '<= ANY',
        '>=' => '>= ANY',
        'IN' => 'IN',
        'NOT IN' => 'NOT IN',
    );

    public $logic;

    public $attribute;
    public $operator;
    public $value;
    
    public $relation;
    public $table;

    public $stack = array();

    public function __construct($sAttribute, $sOperator, $mValue, $sLogic = 'or')
    {
        $this->attribute = $sAttribute;
        $this->operator  = $sOperator ?: '=';
        $this->value     = $mValue;
        $this->logic     = $sLogic;

        if (! isset(self::$_aArrayOperators[$this->operator]))
        {
            $sMessage  = 'Unknown SQL operator: "' . $this->operator .  '".' .  PHP_EOL;
            $sMessage .= 'List of available operators: ' . implode(', ', array_keys(self::$_aArrayOperators)); 
            throw new Exception($sMessage);
        }

        if (is_array($this->value))
        {
            if (! $this->value)
            {
                throw new Exception('Invalid condition value "' . $this->value . '" for attribtue "' . $this->attribtue . '".');
            }

            // Concert objects to object ID's.
            if (is_object($this->value[0]))
            {
                for ($i = 0, $iCount = count($this->value) ; $i < $iCount ; ++$i)
                {
                    $this->value[$i] = $this->value[$i]->id;
                }

            }

            $this->operator = self::arrayfyOperator($this->operator);
        }
        else
        {
            // Concert objects to object ID's.
            if (is_object($this->value))
            {
                $this->value = $this->value->id;
            }
        }
    }

    public static function arrayToSql($aArray, $bUseAliases = true, $bToColumn = true)
    {
        $sSql    = '(';
        $aValues = array();

        $bFirst  = true;

		foreach ($aArray as $aItem)
		{
            $sLogicalOperator = $aItem[0];
            $mItem            = $aItem[1];

            if ($bFirst)
            {
                $bFirst = false;
            }
            else
            {
                $sSql .= ' ' . $sLogicalOperator . ' ';
            }

            if ($mItem instanceof Condition)
            {
                $sSql   .= ' ' . $mItem->toSql($bUseAliases, $bToColumn);
                $aValues = array_merge($aValues, $mItem->flattenValues());
            }
            // $mItem is an array of Conditions.
            else
            {
                list($s, $a) = self::arrayToSql($mItem, $bUseAliases, $bToColumn);

                $sSql   .= $s;
                $aValues = array_merge($aValues, $a);
            }
        }

        $sSql .= ')';

        return array($sSql, $aValues);
    }

    public static function arrayfyOperator($sOperator)
    {
        if (! isset(self::$_aArrayOperators[$sOperator]))
        {
            throw new Exception('Operator "' . $sOperator . '" can not be "arrayfied".');
        }

        return self::$_aArrayOperators[$sOperator];
    }

    public function flattenValues()
    {
        if (is_array($this->value))
        {
            if (is_array($this->value[0]))
            {
                return call_user_func_array('array_merge', $this->value);
            }
            else
            {
                return $this->value;
            }
        }
        else
        {
            return array($this->value);
        }
    }


    public static function leftHandSide($mAttributes, $oTable, $bToColumn = true, $sTableAliasSuffix = '')
    {
        $mColumns = $bToColumn
            ? $oTable->columnRealName($mAttributes)
            : $mAttributes
            ;

        // Construct alias.
        $sTableAlias = (is_object($oTable) ? $oTable->alias : $oTable) .  $sTableAliasSuffix;
        if ($sTableAlias !== '')
        {
            $sTableAlias .= '.';
        }

        if (is_array($mColumns))
        {
            $a = array();
            foreach ($mColumns as $sColumn)
            {
                $a[] = $sTableAlias . $sColumn;
            }

            return '(' . implode(',', $a) . ')';
        }
        else
        {
            return $sTableAlias . $mColumns;
        }
    }

    public static function rightHandSide($mValue)
    {
        $iCount = count($mValue);

        // $mValue is a multidimensional array. Actually it is a array of
        // tuples.
        if (is_array($mValue[0]))
        {
            // Tuple cardinal.
            $iTupleSize = count($mValue[0]);
            
            $sTuple = '(' . str_repeat('?,', $iTupleSize - 1) . '?)';
            $sRes   = '(' . str_repeat($sTuple . ',', $iCount - 1) . $sTuple .  ')';
        }
        // Simple array.
        else
        {
            $sRes = '(' . str_repeat('?,', $iCount - 1) . '?)';
        }

        return $sRes;
    }

	/**
	 * We accept two forms of conditions:
	 * 1) Basic conditions:
	 *      array(
	 *          'my/attribute' => 'myValue',
	 *          ...
	 *      )
	 * 2) Conditions with operator:
	 *      array(
	 *          array('my/attribute', 'myOperator', 'myValue'),
	 *          ...
	 *      )
	 *
	 * Operator: =, !=, IN, NOT IN, >, <, <=, >=.
	 */
    public static function parseConditionArray($aArray)
    {
        $aRes = array();

        $sLogicalOperator = 'AND';

        foreach ($aArray as $mKey => $mValue)
        {
            // It is bound to be a condition.
            if (is_string($mKey))
            {
                $aRes[] = array($sLogicalOperator, new Condition($mKey, null, $mValue));
                // Reset operator.
                $sLogicalOperator = 'AND';
            }
            // It can be a condition, a condition group, or a logical operator.
            else
            {
                // It is a logical operator.
                if ($mValue === 'OR' || $mValue === '||')
                {
                    $sLogicalOperator = 'OR';
                }
                elseif ($mValue === 'AND' || $mValue === '&&')
                {
                    $sLogicalOperator = 'AND';
                }
                // Condition or condition group.
                else
                {
                    // Condition.
                    if (is_string($mValue[0]))
                    {
                        $aRes[] = array($sLogicalOperator, new Condition($mValue[0], $mValue[1], $mValue[2]));
                    }
                    // Condition group.
                    else
                    {
                        $aRes[] = array($sLogicalOperator, self::parseConditionArray($mValue));
                    }

                    // Reset operator.
                    $sLogicalOperator = 'AND';
                }
            }
        }

        return $aRes;
    }

    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        if (!$bUseAliases && $this->table)
        {
            $this->table->alias = '';
        }

        // If condition does not depend of a relation. Construct SQL here.
        if ($this->relation === null)
        {
            $mAttribute = explode(',', $this->attribute);

            // It is a compound attribute.
            /*
            if (isset($mAttribute[1]))
            {
                $sOperator = self::arrayfyOperator($this->operator);
            }
            */

            $sLHS = self::leftHandSide($mAttribute, $this->table, $bToColumn);
            $sRHS = self::rightHandSide($this->value);

            return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
        }
        // Relation class is better placed to do it.
        else
        {
			return $this->relation->condition($this);
        }
    }
}
