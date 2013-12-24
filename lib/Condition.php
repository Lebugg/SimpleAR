<?php
/**
 * This file contains the Condition class.
 *
 * @author Lebugg
 */
namespace SimpleAR;

/**
 * The Condition class modelize a SQL condition (in a WHERE clause).
 */
class Condition
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
     * A Relationship object used when condition is made through a Model relation.
     *
     * Optional
     *
     * @var Relationship
     */
    public $relation;

    /**
     * A Table object corresponding to the attribute's Model.
     *
     * @var Table
     */
    public $table;

    public $type = 'simple';
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

            throw new Exception($sMessage);
        }

        // Set logic and check its validity.
        $this->logic     = $sLogic;
        if (! ($this->logic === 'or' || $o->logic === 'and'))
        {
            throw new Exception('Logical operator "' . $o->logic . '" is not valid.');
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
                $this->operator = '<=>';
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
                if ($mItem->virtual)
                {
                    if ($mItem->subconditions)
                    {
                        list($s, $a) = self::arrayToSql($mItem->subconditions, $bUseAliases, $bToColumn);

                        $sSql   .= '(' . $s . ')';
                        $aValues = array_merge($aValues, $a);
                    }
                }
                else
                {
                    $sSql   .= ' ' . $mItem->toSql($bUseAliases, $bToColumn);
                    $aValues = array_merge($aValues, $mItem->flattenValues());
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
    public function toSql($bUseAliases = true, $bToColumn = true)
    {
        $s = 'toSql_' . $this->type;
        return $this->$s($bUseAliases, $bToColumn);
    }

    public function toSql_exists($bUseAliases, $bToColumn)
    {
        $r = $this->relation;

        if ($r === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        // True for EXISTS, false for NOT EXISTS.
        $b = $this->exists ? '' : 'NOT';

        $sCMColumn = self::leftHandSide($r->cm->column, $r->cm->alias . ($this->depth - 1 ?: ''));

        // Not the same for ManyMany, we check join table.
        if ($r instanceof ManyMany)
        {
            $sTable    = $r->jm->table;
            $sLMColumn = self::leftHandSide($r->jm->from, 'a');
        }
        // For all other Relation type, we check linked table.
        else
        {
            $sTable    = $r->lm->table;
            $sLMColumn = self::leftHandSide($r->lm->column, 'a');
        }

        // Easy subquery.
        // $b contains '' or 'NOT'. It relies on $this->exists value.
        return " $b EXISTS (
                    SELECT NULL
                    FROM {$sTable} a
                    WHERE {$sLMColumn} = {$sCMColumn}
                )";
    }

    public function toSql_relation($bUseAliases, $bToColumn)
    {
        if ($this->relation === null)
        {
            throw new Exception('Cannot transform Condition to SQL because “relation” is not set.');
        }

        $r = $this->relation;

        if ($r instanceof BelongsTo)
        {
            // We check that condition makes sense.
            if ($this->logic === 'and' && isset($this->value[1]))
            {
                throw new Exception('Condition does not make sense: ' . strtoupper($o->operator) . ' operator with multiple values for a ' . __CLASS__ . ' relationship.');
            }

            // Get attribute column name.
            if ($this->attribute === 'id')
            {
                $mColumn = $r->cm->column;
                $oTable  = $r->cm->t;
            }
            else
            {
                $mColumn = $r->lm->t->columnRealName($this->attribute);
                $oTable  = $r->lm->t;
            }

            $sLHS = self::leftHandSide($mColumn, $oTable->alias . ($this->depth ?: ''));
            $sRHS = self::rightHandSide($this->value);

            return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
        }

        elseif ($r instanceof HasOne)
        {
            // We check that condition makes sense.
            if ($this->logic === 'and' && isset($this->value[1]))
            {
                throw new Exception('Condition does not make sense: "' . strtoupper($this->operator) . '" operator with multiple values for a ' . __CLASS__ . ' relationship.');
            }

            $mColumn = $r->lm->t->columnRealName($this->attribute);
            $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . ($this->depth ?: ''));
            $sRHS = self::rightHandSide($this->value);

            return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
        }

        elseif ($r instanceof HasMany)
        {
            $oLM = $r->lm;
            $oCM = $r->cm;

            $iPreviousDepth = $this->depth <= 1 ? '' : $this->depth - 1;
            $iDepth = $this->depth ?: '';

            if ($this->logic === 'or')
            {
                $mColumn = $oLM->t->columnRealName($this->attribute);
                $sLHS = self::leftHandSide($mColumn, $oLM->t->alias . '_sub');
                $sRHS = self::rightHandSide($this->value);

                $sLHS_LMColumn = self::leftHandSide($oLM->column, $oLM->t->alias . '_sub');
                $sLHS_CMColumn = self::leftHandSide($oCM->column, $oCM->t->alias . $iPreviousDepth);

                return "EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}_sub
                            WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                            AND   {$sLHS} {$this->operator} {$sRHS}
                        )";
            }
            else // logic == 'and'
            {
                $mColumn = $oLM->t->columnRealName($this->attribute);
                $sLHS2 = self::leftHandSide($mColumn, $oLM->t->alias . '_sub');
                $sLHS3 = self::leftHandSide($mColumn, $oLM->t->alias . '_sub2');
                $sRHS  = self::rightHandSide($this->value);

                $sLHS_LMColumn = self::leftHandSide($oLM->column, $oLM->t->alias . '_sub2');
                $sLHS_CMColumn = self::leftHandSide($oCM->column, $oCM->t->alias . $iPreviousDepth);

                return "NOT EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}_sub
                            WHERE {$sLHS2} {$this->operator} {$sRHS}
                            AND {$sLHS2} NOT IN (
                                SELECT {$sLHS3}
                                FROM $oLM->table {$oLM->alias}_sub2
                                WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                                AND   {$sLHS3}         = {$sLHS2}
                            )
                        )";
            }
        }

        else // ManyMany
        {
            $mColumn = $r->lm->t->columnRealName($this->attribute);

            $iPreviousDepth = $this->depth <= 1 ? '' : $this->depth - 1;
            $iDepth = $this->depth ?: '';

            if ($this->logic === 'or')
            {
                $sRHS = self::rightHandSide($this->value);
                if ($this->attribute === 'id')
                {
                    $sLHS = self::leftHandSide($r->jm->to, $r->jm->alias . $iDepth);
                }
                else
                {
                    $mColumn = $r->lm->t->columnRealName($this->attribute);
                    $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . $iDepth);
                }

                return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
            }
            else // $this->logic === 'and'
            {
                $mColumn = $r->lm->t->columnRealName($this->attribute);
                $sLHS = self::leftHandSide($mColumn, $r->lm->t->alias . $iDepth);
                $sRHS = self::rightHandSide($this->value);

                $sCond_JMFrom  = self::leftHandSide($r->jm->from,   $this->jm->alias . $iDepth);
                $sCond_JMFrom2 = self::leftHandSide($r->jm->from,   $this->jm->alias . '_sub');
                $sCond_JMTo2   = self::leftHandSide($r->jm->to,     $this->jm->alias . '_sub');
                $sCond_LM2     = self::leftHandSide($r->lm->column, $this->lm->alias . '_sub');

                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$r->lm->table} {$this->lm->alias}_sub
                            WHERE {$sLHS} {$this->operator} {$sRHS}
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$r->jm->table} {$this->jm->alias}_sub
                                WHERE {$sCond_JMFrom} = {$sCond_JMFrom2}
                                AND   {$sCond_JMTo2}  = {$sCond_LM2}
                            )
                        )";

            }
        }
    }

    public function toSql_simple($bUseAliases, $bToColumn)
    {
        $mColumns = $bToColumn   ? $this->table->columnRealName($this->attribute) : $this->attribute;
        $sAlias   = $bUseAliases ? $this->table->alias : '';

        $sLHS = self::leftHandSide($mColumns, $sAlias);
        $sRHS = self::rightHandSide($this->value);

        return $sLHS . ' ' . $this->operator . ' ' . $sRHS;
    }
}
