<?php
/**
 * This file contains the Condition class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Query;

require 'conditions/Exists.php';
require 'conditions/Relation.php';
require 'conditions/Simple.php';

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
abstract class Condition
{
    /**
     * List of available operators.
     *
     * The keys are the available operators; The values are the corresponding operators when
     * condition is made on multiple values.
     *
     * @var array
     */
    protected static $_aOperators = array(
        '='  => 'IN',
        '!=' => 'NOT IN',
        '<'  => '< ANY',
        '>'  => '> ANY',
        '<=' => '<= ANY',
        '>=' => '>= ANY',
        'IN' => 'IN',
        'NOT IN' => 'NOT IN',
    );

    const DEFAULT_OP = '=';

    const DEFAULT_LOGICAL_OP = 'AND';

    /**
     * The condition logic.
     *
     * Can take these values: "and" or "or".
     *
     * @var string
     */
    public $logic;

    /**
     * Attribute(s) the condition is made on.
     *
     * @var string|array
     */
    public $attribute;

    /**
     * The condition operator.
     *
     * @var string
     */
    public $operator;

    /**
     * The condition value(s).
     *
     * It contains one or several values that attribute(s) must verify to make condition true.
     *
     * @var mixed
     */
    public $value;
    

    /**
     * A Table object corresponding to the attribute's Model.
     *
     * @var Table
     */
    public $table;

    public $depth = 0;
    public $virtual = false;
    public $subconditions = array();

    /**
     * Constructor
     *
     * @param string|array $sAttribute Attribute(s) of condition.
     * @param string       $sOperator  Operator to use.
     * @param mixed        $mValue     Value(s) to test attribute(s) against.
     * @param string       $sLogic     The logic of the condition.
     *
     * @throws Exception if operator, value, or logic is invalid.
     */
    public function __construct($sAttribute, $sOperator, $mValue, $sLogic = 'or')
    {
        $this->attribute = $sAttribute;

        // Set operator and check its validity.
        $this->operator  = $sOperator ?: '=';
        if (! isset(self::$_aOperators[$this->operator]))
        {
            $sMessage  = 'Unknown SQL operator: "' . $this->operator .  '".' .  PHP_EOL;
            $sMessage .= 'List of available operators: ' . implode(', ', array_keys(self::$_aOperators)); 

            throw new \SimpleAR\Exception($sMessage);
        }

        // Set logic and check its validity.
        $this->logic     = $sLogic;
        if (! ($this->logic === 'or' || $o->logic === 'and'))
        {
            throw new \SimpleAR\Exception('Logical operator "' . $o->logic . '" is not valid.');
        }

        // Set value.
        // $mValue can be: an object, a scalar value or an array.
        // If $mValue is an array, it can contain: objects, scalar values or 1-dimension array.

        // Specific case: applying (array) on object would transform it, not wrap it.
        if (is_object($mValue))
        {
            $this->value = $mValue->id;
        }
        else
        {
            $this->value = array();
            // Extract IDs if objects are passed.
            foreach ((array) $mValue as $mVal)
            {
                $this->value[] = is_object($mVal) ? $mVal->id : $mVal;
            }

            if (!isset($this->value[0]))
            {
                $this->value    = NULL;
                $this->operator = $this->operator === '=' ? 'IS' : 'IS NOT';
                //throw new Exception('Invalid condition value: ' . $mValue . '.');
            }

            // Several values, we have to *arrayfy* the operator.
            if (isset($this->value[1]))
            {
                $this->operator = self::$_aOperators[$this->operator];
            }
            // Else, we do not need an array.
            else
            {
                $this->value = $this->value[0];
            }
        }
    }

    /**
     * Generate a valid SQL string out of a Condition array.
     *
     * @param array $aArray         The Condition array.
     * @param bool  $bUseAliases    Should we prefix columns with aliases? Default: true.
     * @param bool  $bToColumn      Do condition's attribute have to be converted to column's names?Have
     *
     * @return string The SQL string.
     */
    public static function arrayToSql($aArray, $bUseAliases = true, $bToColumn = true)
    {
        $sSql    = '';
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
                // Clearer.
                $oCondition = $mItem;

                if ($oCondition->virtual)
                {
                    if ($oCondition->subconditions)
                    {
                        list($s, $a) = self::arrayToSql($oCondition->subconditions, $bUseAliases, $bToColumn);

                        $sSql   .= '(' . $s . ')';
                        $aValues = array_merge($aValues, $a);
                    }
                }
                else
                {
                    $sSql .= ' ' . $oCondition->toSql($bUseAliases, $bToColumn);
                    $m     = $oCondition->flattenValues();

                    // $m may be null and we don't want it because it is handled by a IS [NOT] NULL
                    // condition.
                    if ($m)
                    {
                        $aValues = array_merge($aValues, $m);
                    }
                }
            }
            // $mItem is an array of Conditions.
            else
            {
                list($s, $a) = self::arrayToSql($mItem, $bUseAliases, $bToColumn);

                $sSql   .= '(' . $s . ')';
                $aValues = array_merge($aValues, $a);
            }
        }

        return array($sSql, $aValues);
    }

    /**
     * This function is used to format value array for PDO.
     *
     * @return array
     *      Condition values.
     */
    public function flattenValues()
    {
        if ($this->value === null) { return; }

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

    /**
     * Creates the left hand side of an SQL condition.
     *
     * @param string|array  $mColumns    One or several columns the condition is made on.
     * @param string|Table  $sTableAlias The table alias to use to prefix columns.
     *
     * @return string A valid left hand side SQL condition.
     */
    public static function leftHandSide($mColumns, $sTableAlias = '')
    {
        // Add dot to alias to prevent additional concatenations in foreach.
        if ($sTableAlias !== '')
        {
            $sTableAlias .= '.';
        }

        $a = array();
        foreach ((array) $mColumns as $sColumn)
        {
            $a[] = $sTableAlias . $sColumn;
        }

        // If several conditions, we have to wrap them with brackets in order to assure about
        // conditional operators priority.
        return isset($a[1]) ? '(' . implode(',', $a) . ')' : $a[0];
    }

    /**
     * Creates the left hand side of an SQL condition.
     *
     * Condition is constructed with '?'. So values must be bind to query in some other place.
     * @see Condition::flattenValues()
     *
     * @return string A valid right hand side SQL condition.
     */
    public static function rightHandSide($mValue)
    {
        $iCount = count($mValue);

        // $mValue is a multidimensional array. Actually, it is a array of
        // tuples.
        if (is_array($mValue))
        {
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
        }
        else
        {
            $sRes = '?';
        }

        return $sRes;
    }

	/**
     *
	 * We accept two forms of conditions:
	 * 1) Basic conditions:
     *  ```php
	 *      array(
	 *          'my/attribute' => 'myValue',
	 *          ...
	 *      )
     *  ```
	 * 2) Conditions with operator:
     *  ```php
	 *      array(
	 *          array('my/attribute', 'myOperator', 'myValue'),
	 *          ...
	 *      )
     *  ```
     *
     * Of course, you can combine both form in a same condition array.
     *
     * By default, conditions are linked with a AND operator but you can use an
     * OR by specifying it in condition array:
     *  ```php
	 *      array(
	 *          'attr1' => 'val1',
     *          'OR',
     *          'attr2' => 'val2',
     *          'attr3' => 'val3,
	 *      )
     *  ```
     *
     * This correspond to the following exhaustive array:
     *  ```php
	 *      array(
	 *          'attr1' => 'val1',
     *          'OR',
     *          'attr2' => 'val2',
     *          'AND',
     *          'attr3' => 'val3,
	 *      )
     *  ```
	 *
     * You can nest condition arrays. Example:
     *  ```php
	 *      array(
	 *          array(
	 *              'attr1' => 'val1',
     *              'attr2' => 'val2',
	 *          )
     *          'OR',
	 *          array(
     *              'attr3' => 'val3,
     *              'attr1' => 'val4,
	 *          )
	 *      )
     *  ```
     *
     * So we come with this condition array syntax tree:
     *  ```php
     *  condition_array:
     *      array(
     *          [condition | condition_array | (OR | AND)] *
     *      );
     *
     *  condition:
     *      [
     *          'attribute' => 'value'
     *          |
     *          array('attribute', 'operator', 'value')
     *      ]
     *  attribute: <string>
     *  operator: <string>
     *  value: <mixed>
     *  ```
     *
	 * Operators: =, !=, IN, NOT IN, >, <, <=, >=.
     *
     * @param array $aArray The condition array to parse.
     *
     * @return array A well formatted condition array.
	 */
    /*
    public static function parseConditionArray($aArray)
    {
        $aRes = array();

        $sLogicalOperator = 'AND';

        foreach ($aArray as $mKey => $mValue)
        {
            // It is bound to be a condition.
            // 'myAttribute' => 'myValue'
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
                    if (isset($mValue[0]) && is_string($mValue[0]))
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
    */

    /**
     * This function transforms the Condition object into SQL.
     *
     * @param bool $bUseAliases Should table aliases be used?
     * @param bool $bToColumn   Should attributes be transformed to columns?
     *
     * @retutn string A valid SQL condition string.
     */
    public abstract function toSql($bUseAliases = true, $bToColumn = true);
}
