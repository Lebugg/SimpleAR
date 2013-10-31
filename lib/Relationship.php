<?php
namespace SimpleAR;

abstract class Relationship
{
    protected static $_oDb;
    protected static $_oConfig;

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

    protected $_oLM;
    protected $_oCM;

    protected $_bOnDeleteCascade = false;
    protected $_sLoadMode        = 'explicit';
    protected $_sFilter          = null;
    protected $_sName;
    protected $_sType;
	protected $_aConditions = array();
	protected $_aOrder      = array();

    private static $_aDefaults = array(
        'load_mode' => 'explicit',
    );

    private static $_aTypeToClass = array(
        'belongs_to' => 'BelongsTo',
        'has_one'    => 'HasOne',
        'has_many'   => 'HasMany',
        'many_many'  => 'ManyMany',
    );

    protected function __construct($a, $sCMClass)
    {
        if (! self::$_oDb)     { self::$_oDb     = Database::instance(); }
        if (! self::$_oConfig) { self::$_oConfig = Config::instance();   }

        $this->_sType = $a['type'];

        $this->_oCM = new \StdClass();
        $this->_oCM->class     = $sCMClass;
        $this->_oCM->t         = $sCMClass::table();
        $this->_oCM->table     = $this->_oCM->t->name();
        $this->_oCM->alias     = $this->_oCM->t->alias();

        $this->_oLM = new \StdClass();
        $this->_oLM->class = $s = $a['model'] . self::$_oConfig->modelClassSuffix;
        $this->_oLM->t     = $s::table();
        $this->_oLM->table = $this->_oLM->t->name();
        $this->_oLM->alias = $this->_oLM->t->alias();
        $this->_oLM->pk    = $this->_oLM->t->primaryKey();

        if (isset($a['on_delete_cascade'])) { $this->_bOnDeleteCascade = $a['on_delete_cascade']; }
        if (isset($a['filter']))            { $this->_sFilter          = $a['filter']; }
        if (isset($a['load_mode']))         { $this->_sLoadMode        = $a['load_mode']; }
		if (isset($a['conditions']))		{ $this->_aConditions	   = $a['conditions']; }
		if (isset($a['order']))				{ $this->_aOrder		   = $a['order']; }
    }

    public static function arrayfy(&$o)
    {
        $o->originalOperator = $o->operator;

        if ($o->valueCount <= 1)
        {
            return;
        }

        if (! isset(self::$_aArrayOperators[$o->operator]))
        {
            throw new Exception('Operator "' . $o->operator . '" can not be "arrayfied".');
        }

        $o->operator = self::$_aArrayOperators[$o->operator];
    }


    public abstract function condition($o);

	public function conditions()
	{
		return $this->_aConditions;
	}

    public static function construct($sName, $a, $sCMClass)
    {
        $s = 'SimpleAR\\' . self::$_aTypeToClass[$a['type']];

        $o = new $s($a, $sCMClass);
        $o->name($sName);

        return $o;
    }

    public static function constructConditionRightHandSide($iValuesCount)
    {
        return $iValuesCount > 1
            ? '(' . str_repeat('?,', $iValuesCount - 1) . '?)'
            : '?';
    }


    public function deleteLinkedModel($mValue)
    {
        // It handles both cases: simple and compound keys.
        if (is_string($this->_oLM->column))
        {
            self::$_oDb->query('DELETE FROM ' . $this->_oLM->table . ' WHERE ' . $this->_oLM->column . ' = ?', $mValue);
        }
        else
        {
            self::$_oDb->query('DELETE FROM ' . $this->_oLM->table . ' WHERE ' . implode(' = ? AND ', $this->_oLM->column) . ' = ?', $mValue);
        }
    }

    public function filter()
    {
        return $this->_sFilter;
    }

    public abstract function insert($oCM, $oLM);

    public function joinLinkedModel(&$aTablesIn)
    {
        if (!in_array($this->_oLM->table, $aTablesIn))
        {
            $aTablesIn[] = $this->_oLM->table;
            return " JOIN {$this->_oLM->table} {$this->_oLM->alias} ON {$this->_oCM->alias}.{$this->_oCM->column} = {$this->_oLM->alias}.{$this->_oLM->column}";
        }
    }

    public function joinAsLast(&$aTablesIn, $oData)
    {
        return $this->joinLinkedModel($aTablesIn);
    }

    public function keyFrom($bColumnName = false)
    {
        return $bColumnName ? $this->_oCM->column : $this->_oCM->attribute;
    }

    public function keyTo($bColumnName = false)
    {
        return $bColumnName ? $this->_oLM->column : $this->_oLM->attribute;
    }

    public function linkedModelClass()
    {
        return $this->_oLM->class;
    }

    public function linkedModelTable()
    {
        return $this->_oLM->table;
    }

    public static function loadMode($aRelation)
    {
        return isset($aRelation['load_mode']) ? $aRelation['load_mode'] : self::$_aDefaults['load_mode'];
    }

    public function name($sName = null)
    {
        if ($sName) {
            $this->_sName = $sName;
        } else {
            return $this->_sName;
        }
    }

    public function onDeleteCascade()
    {
        return $this->_bOnDeleteCascade;
    }

	public function order()
	{
		return $this->_aOrder;
	}

    public function type()
    {
        return $this->_sType;
    }


    protected static function _checkConditionValidity($o)
    {
        if ($o->valueCount > 1 && !isset(self::$_aArrayOperators[$o->operator]))
        {
            throw new Exception('Operator "' . $o->operator . '" is not valid for multiple values.');
        }

        if ($o->logic != 'or' && $o->logic != 'and')
        {
            throw new Exception('Logical operator "' . $o->logic . '" is not valid.');
        }
    }
}

class BelongsTo extends Relationship
{
    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);

        $sLMClass = $this->_oLM->class;

        $this->_oCM->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
		if (isset($a['key_from']))
		{
			$this->_oCM->attribute = $a['key_from'];
		}
		else // We have to choose a default one.
		{
			$sSuffix = self::$_oConfig->modelClassSuffix;
			
			if ($sSuffix)
			{
				$this->_oCM->attribute = strtolower(strstr($sLMClass, self::$_oConfig->modelClassSuffix, true)) . self::$_oConfig->foreignKeySuffix;
			}
			else
			{
				$this->_oCM->attribute = strtolower($sLMClass) . self::$_oConfig->foreignKeySuffix;
			}
		}
        $this->_oCM->column    = $this->_oCM->t->columnRealName($this->_oCM->attribute);
        $this->_oCM->pk        = $this->_oCM->t->primaryKey();

        $this->_oLM->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->_oLM->column    = $this->_oLM->t->columnRealName($this->_oLM->attribute);
    }

    public function condition($o)
    {
        static::_checkConditionValidity($o);
        self::arrayfy($o);

        if ($o->attribute == 'id') // Condition is made on LM ID. We just have to
                                   // make the condition on the CM field that link on LM pk.
        {
            if (is_string($this->_oLM->pk)) // Classic case: simple primary key.
            {
                $sRightHandSide = self::constructConditionRightHandSide($o->valueCount);
                return "{$this->_oCM->alias}.{$this->_oCM->column} {$o->operator} $sRightHandSide";
            }
            else // LM pk is an array. It means that on CM side, condition is made with several columns.
            {
                $aTmp  = array();
                $aTmp2 = array();

                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
                    foreach ($this->_oCM->column as $sColumn)
                    {
                        $aTmp[] = "{$this->_oCM->alias}.{$sColumn} {$o->originalOperator} ?";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                return '(' . implode(' OR ', $aTmp2) . ')';
            }
        }
        else // Condition is made on a LM attribute (not the primary key).
        {
            if (is_string($o->attribute))
            {
                $sColumn        = $this->_oLM->t->columnRealName($o->attribute);
                $sRightHandSide = self::constructConditionRightHandSide($o->valueCount);
                return "{$this->_oLM->alias}.$sColumn $o->operator $sRightHandSide";
            }
            else
            {
                $aTmp  = array();
                $aTmp2 = array();

                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
					$aColumns = $this->_oLM->t->columnRealName($o->attribute);

                    foreach ($aColumns as $sColumn)
                    {
                        $aTmp[] = "{$this->_oLM->alias}.{$sColumn} {$o->originalOperator} ?";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                return '(' . implode(' OR ', $aTmp2) . ')';
            }
        }
    }

    public function insert($oCM, $oLM)
    {
    }

    public function joinAsLast(&$aTablesIn, $o)
    {
        if ($o->attribute != 'id') {
            return $this->joinLinkedModel($aTablesIn);
        }
    }

    protected static function _checkConditionValidity($o)
    {
        parent::_checkConditionValidity($o);

        if ($o->logic == 'and' && $o->valueCount > 1)
        {
            throw new Exception('Condition does not make sense: ' . strtoupper($o->operator) . ' operator with multiple values for a ' . __CLASS__ . ' relationship.');
        }
    }

}

class HasOne extends Relationship
{
    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);

        $this->_oCM->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->_oCM->column    = $this->_oCM->t->columnRealName($this->_oCM->attribute);
        $this->_oCM->pk        = $this->_oCM->t->primaryKey();

        $sLMClass = $this->_oLM->class;
		if (isset($a['key_to']))
		{
			$this->_oLM->attribute = $a['key_to'];
		}
		else // We have to choose a default one.
		{
			$sSuffix = self::$_oConfig->modelClassSuffix;
			
			if ($sSuffix)
			{
				$this->_oLM->attribute = strtolower(strstr($sCMClass, self::$_oConfig->modelClassSuffix, true)) . self::$_oConfig->foreignKeySuffix;
			}
			else
			{
				$this->_oLM->attribute = strtolower($sCMClass) . self::$_oConfig->foreignKeySuffix;
			}
		}
        $this->_oLM->column    = $this->_oLM->t->columnRealName($this->_oLM->attribute);
    }

    public function condition($o)
    {
        static::_checkConditionValidity($o);
        self::arrayfy($o);

		$mColumn = $this->_oLM->t->columnRealName($o->attribute);

        if (is_string($mColumn))
        {
            $sRightHandSide = self::constructConditionRightHandSide($o->valueCount);

            return "{$this->_oLM->alias}.$mColumn $o->operator $sRightHandSide";
        }
        else
        {
            $aTmp  = array();
            $aTmp2 = array();

            for ($i = 0 ; $i < $o->valueCount ; ++$i)
            {
                foreach ($mColumn as $sColumn)
                {
                    $aTmp[] = "{$this->_oLM->alias}.{$sColumn} {$o->originalOperator} ?";
                }

                $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                $aTmp    = array();
            }

            // The 'OR' simulates a IN statement for compound primary keys.
            return '(' . implode(' OR ', $aTmp2) . ')';
        }
    }

    public function insert($oCM, $oLM)
    {
    }

    protected static function _checkConditionValidity($o)
    {
        parent::_checkConditionValidity($o);

        if ($o->logic == 'and' && $o->valueCount > 1)
        {
            throw new Exception('Condition does not make sense: ' . strtoupper($o->operator) . ' operator with multiple values for a ' . __CLASS__ . ' relationship.');
        }

    }

}

class HasMany extends Relationship
{
    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);
        
        $this->_oCM->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->_oCM->column    = $this->_oCM->t->columnRealName($this->_oCM->attribute);
        $this->_oCM->pk        = $this->_oCM->t->primaryKey();

        $sLMClass = $this->_oLM->class;
		
		if (isset($a['key_to']))
		{
			$this->_oLM->attribute = $a['key_to'];
		}
		else // We have to choose a default one.
		{
			$sSuffix = self::$_oConfig->modelClassSuffix;
			
			if ($sSuffix)
			{
				$this->_oLM->attribute = strtolower(strstr($sCMClass, self::$_oConfig->modelClassSuffix, true)) . self::$_oConfig->foreignKeySuffix;
			}
			else
			{
				$this->_oLM->attribute = strtolower($sCMClass) . self::$_oConfig->foreignKeySuffix;
			}
		}

        $this->_oLM->column = $this->_oLM->t->columnRealName($this->_oLM->attribute);
    }

    public function condition($o)
    {
        self::_checkConditionValidity($o);
        self::arrayfy($o);

        $sValue = self::constructConditionRightHandSide($o->valueCount);
 

        $oLM     = $this->_oLM;
        $oCM     = $this->_oCM;
		$mColumn = $oLM->t->columnRealName($o->attribute);

        if ($o->logic == 'or')
        {
            if (is_string($mColumn))
            {
                return "EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}2
                            WHERE {$oLM->alias}2.$oLM->column = {$oCM->alias}.{$oCM->column}
                            AND   {$oLM->alias}2.$mColumn $o->operator $sValue
                        )";
            }
            else
            {
                $aTmp  = array();
                $aTmp2 = array();

                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
                    foreach ($mColumn as $sColumn)
                    {
                        $aTmp[] = "{$oLM->alias}2.{$sColumn} {$o->originalOperator} ?";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                $sCondition = '(' . implode(' OR ', $aTmp2) . ')';

                return "EXISTS (
                    SELECT NULL
                    FROM $oLM->table {$oLM->alias}2
                    WHERE {$oLM->alias}2.$oLM->column = {$oCM->alias}.{$oCM->column}
                    AND   $sCondition
                )";
            }
        }
        else // logic == 'and'
        {
            if (is_string($mColumn))
            {
                return "NOT EXISTS (
                            SELECT NULL
                            FROM $oLM->table {$oLM->alias}2
                            WHERE {$oLM->alias}2.$mColumn $o->operator $sValue
                            AND {$oLM->alias}2.$mColumn NOT IN (
                                SELECT {$oLM->alias}3.$mColumn
                                FROM $oLM->table {$oLM->alias}3
                                WHERE {$oLM->alias}3.$oLM->column = {$oCM->alias}.{$oCM->column}
                                AND   {$oLM->alias}3.$mColumn     = {$oLM->alias}2.$mColumn
                            )
                        )";
            }
            else
            {
                $aTmp  = array();
                $aTmp2 = array();

                $aOtherTmp  = array();
                $aOtherTmp2 = array();
                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
                    foreach ($mColumn as $sColumn)
                    {
                        $aTmp[]      = "{$oLM->alias}2.{$sColumn} {$o->originalOperator} ?";
                        $aOtherTmp[] = "{$oLM->alias}3.{$sColumn} = {$oLM->alias}2.{$sColumn}";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();

                    $aOtherTmp2[] = '(' . implode(' AND ', $aOtherTmp) . ')';
                    $aOtherTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                $sCondition      = '(' . implode(' OR ', $aTmp2) . ')';
                $sOtherCondition = '(' . implode(' OR ', $aOtherTmp2) . ')';

                // I don't think it's correct.
                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$oLM->table} {$oLM->alias}2
                            WHERE {$sCondition}
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$oLM->table} {$oLM->alias}3
                                AND   {$sOtherCondition}
                                WHERE {$oLM->alias}3.{$oLM->column} = {$oCM->alias}.{$oCM->column}
                            )
                        )";
            }
        }
    }

    public function insert($oCM, $oLM)
    {
    }

    public function joinAsLast(&$aTablesIn, $o)
    {
        if ($o->logic != 'or') {
            return $this->joinLinkedModel($aTablesIn);
        }
    }

}

class ManyMany extends Relationship
{
    private $_oJM;

    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);

        $this->_oCM->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->_oCM->column    = $this->_oCM->t->columnRealName($this->_oCM->attribute);
        $this->_oCM->pk        = $this->_oCM->t->primaryKey();

        $sLMClass = $this->_oLM->class;
        $this->_oLM->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->_oLM->column    = $this->_oLM->t->columnRealName($this->_oLM->attribute);

        $this->_oJM = new \StdClass();

        if (isset($a['join_model']))
        {
            $this->_oJM->class = $s = $a['join_model'] . self::$_oConfig->modelClassSuffix;
            $this->_oJM->t     = $s::table();
            $this->_oJM->table = $this->_oJM->t->name();
            $this->_oJM->from  = $this->_oJM->t->columnRealName(isset($a['join_from']) ? $a['join_from'] : strtolower(strstr($sCMClass, self::$_oConfig->modelClassSuffix, true)) . self::$_oConfig->foreignKeySuffix);
            $this->_oJM->to    = $this->_oJM->t->columnRealName(isset($a['join_to'])   ? $a['join_to']   : strtolower(strstr($sLMClass, self::$_oConfig->modelClassSuffix, true)) . self::$_oConfig->foreignKeySuffix);
        }
        else
        {
            $this->_oJM->class = null;
            $this->_oJM->table = isset($a['join_table']) ? $a['join_table'] : $this->_oCM->table . '_' . $this->_oLM->table;
            $this->_oJM->from  = isset($a['join_from'])  ? $a['join_from']  : $this->_oCM->pk;
            $this->_oJM->to    = isset($a['join_to'])    ? $a['join_to']    : $this->_oLM->pk;
        }

        $this->_oJM->alias = strtolower($this->_oJM->table);
    }

    public function condition($o)
    {
        self::_checkConditionValidity($o);
        self::arrayfy($o);


		$mColumn = $this->_oLM->t->columnRealName($o->attribute);

        if ($o->logic == 'or')
        {
            if (is_string($mColumn))
            {
                $sRightHandSide = self::constructConditionRightHandSide($o->valueCount);

                return $o->attribute == 'id'
                    ? "{$this->_oJM->alias}.{$this->_oJM->to} $o->operator $sRightHandSide"
                    : "{$this->_oLM->alias}.{$mColumn}        $o->operator $sRightHandSide";
            }
            else
            {
                $aTmp  = array();
                $aTmp2 = array();

                $oM = $o->attribute == 'id' ? $this->_oJM : $this->_oLM;

                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
                    foreach ($this->_oCM->column as $sColumn)
                    {
                        $aTmp[] = "{$oM->alias}.{$sColumn} {$o->originalOperator} ?";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                return '(' . implode(' OR ', $aTmp2) . ')';
            }
        }

        if ($o->logic == 'and')
        {
            if (is_string($mColumn))
            {
                $sRightHandSide = self::constructConditionRightHandSide($o->valueCount);

                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$this->_oLM->table} {$this->_oLM->alias}2
                            WHERE {$this->_oLM->alias}2.{$mColumn} $o->operator $sRightHandSide
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$this->_oJM->table} {$this->_oJM->alias}2
                                WHERE {$this->_oJM->alias}.{$this->_oJM->from} = {$this->_oJM->alias}2.{$this->_oJM->from}
                                AND   {$this->_oJM->alias}2.{$this->_oJM->to}  = {$this->_oLM->alias}2.{$this->_oLM->column}
                            )
                        )";
            }
            else
            {
                $aTmp  = array();
                $aTmp2 = array();

                $oM = $o->attribute == 'id' ? $this->_oJM : $this->_oLM;

                for ($i = 0 ; $i < $o->valueCount ; ++$i)
                {
                    foreach ($this->_oCM->column as $sColumn)
                    {
                        $aTmp[] = " {$this->_oLM->alias}2.{$sColumn} {$o->originalOperator} ?";
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound primary keys.
                $sCondition = '(' . implode(' OR ', $aTmp2) . ')';
                
                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$this->_oLM->table} {$this->_oLM->alias}2
                            WHERE $sCondition
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$this->_oJM->table} {$this->_oJM->alias}2
                                WHERE {$this->_oJM->alias}.{$this->_oJM->from} = {$this->_oJM->alias}2.{$this->_oJM->from}
                                AND   {$this->_oJM->alias}2.{$this->_oJM->to}  = {$this->_oLM->alias}2.{$this->_oLM->column}
                            )
                        )";
            }
        }
    }

    public function deleteJoinModel($mValue)
    {
        self::$_oDb->query('DELETE FROM ' . $this->_oJM->table . ' WHERE ' . implode(' = ? AND ', $this->_oJM->from) . ' = ?', $mValue);
    }

    public function deleteLinkedModel($mValue)
    {
        if (is_string($this->_oLM->pk))
        {
            $sCondition = "a.{$this->_oLM->pk} = b.{$this->_oJM->to}";
        }
        else
        {
            $aTmp  = array();

            $oM = $o->attribute == 'id' ? $this->_oJM : $this->_oLM;

			foreach ($this->_oLM->pk as $i => $sKey)
			{
				$aTmp[] = "a.{$sKey} = b.{$this->_oJM->to[$i]}";
			}

			$sCondition = '(' . implode(' AND ', $aTmp) . ')';
        }

        $sQuery =  "DELETE FROM {$this->_oLM->table} a
                    WHERE EXISTS (
                        SELECT NULL
                        FROM {$this->_oJM->table} b
                        WHERE b." . implode(' = ? AND b.', $this->_oJM->from) ." = ?
                        AND $sCondition
                    )";

        self::$_oDb->query($sQuery, $mValue);
    }

    public function insert($oCM, $oLM)
    {
        $sQuery = 'INSERT INTO ' . $this->_oJM->table . ' (' . $this->_oJM->from . ',' . $this->_oJM->to . ') VALUES (?,?)';
        try {
            self::$_oDb->query($sQuery, array($oCM->{$this->_oCM->attribute}, $oLM->{$this->_oLM->attribute}));
        } catch(Exception $oEx) {
            throw new DuplicateKeyException(get_class($oCM) . '(' . $oCM->id . ') is already linked to ' . get_class($oLM) . '(' . $oLM->id . ').');
        }
    }

    public function joinLinkedModel(&$aTablesIn)
    {
        return $this->_joinJM($aTablesIn) . ' ' .  $this->_joinLM($aTablesIn);
    }

    public function joinAsLast(&$aTablesIn, $o)
    {
        $sRes = '';

        // We always want to join the middle table.
        $sRes .= $this->_joinJM($aTablesIn);

        // And, under certain conditions, the linked table.
        if (!$o->logic == 'or' || $o->attribute != 'id') {
            $sRes .= $this->_joinLM($aTablesIn);
        }

        return $sRes;
    }

    public function joinKeyFrom()
    {
        return $this->_oJM->from;
    }

    public function joinKeyTo()
    {
        return $this->_oJM->to;
    }

    public function joinTable()
    {
        return $this->_oJM->table;
    }

    private function _joinJM(&$aTablesIn)
    {
        if (!in_array($this->_oJM->table, $aTablesIn)) {
            $aTablesIn[] = $this->_oJM->table;
            return " JOIN {$this->_oJM->table} {$this->_oJM->alias} ON {$this->_oCM->alias}.{$this->_oCM->column} = {$this->_oJM->alias}.{$this->_oJM->from}";
        }
    }

    private function _joinLM(&$aTablesIn)
    {
        if (!in_array($this->_oLM->table, $aTablesIn)) {
            $aTablesIn[] = $this->_oLM->table;
            return " JOIN {$this->_oLM->table} {$this->_oLM->alias} ON {$this->_oJM->alias}.{$this->_oJM->to} = {$this->_oLM->alias}.{$this->_oLM->pk}";
        }
    }

}
