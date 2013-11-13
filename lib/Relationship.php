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
        $this->_oCM->table     = $this->_oCM->t->name;
        $this->_oCM->alias     = $this->_oCM->t->alias;

        $this->_oLM = new \StdClass();
        $this->_oLM->class = $s = $a['model'] . self::$_oConfig->modelClassSuffix;
        $this->_oLM->t     = $s::table();
        $this->_oLM->table = $this->_oLM->t->name;
        $this->_oLM->alias = $this->_oLM->t->alias;
        $this->_oLM->pk    = $this->_oLM->t->primaryKey;

        if (isset($a['on_delete_cascade'])) { $this->_bOnDeleteCascade = $a['on_delete_cascade']; }
        if (isset($a['filter']))            { $this->_sFilter          = $a['filter']; }
        if (isset($a['load_mode']))         { $this->_sLoadMode        = $a['load_mode']; }
		if (isset($a['conditions']))		{ $this->_aConditions	   = $a['conditions']; }
		if (isset($a['order']))				{ $this->_aOrder		   = $a['order']; }
    }

    public static function arrayfy(&$o)
    {
        $o->originalOperator = $o->operator;

        if (!isset($o->value[1]))
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

    public function currentModelPrimaryKey()
    {
        return $this->_oCM->pk;
    }

    public function currentModelTable()
    {
        return $this->_oCM->table;
    }

    public function currentModelTableAlias()
    {
        return $this->_oCM->table;
    }

    public function deleteLinkedModel($mValue)
    {
        $oQuery = Query::delete(array($this->_oLM->attribute => $mValue), $this->_oLM->class);
        $oQuery->run();
    }

    public function filter()
    {
        return $this->_sFilter;
    }

    public abstract function insert($oCM, $oLM);

    public function joinLinkedModel()
    {
		return " JOIN {$this->_oLM->table} {$this->_oLM->alias} ON {$this->_oCM->alias}.{$this->_oCM->column} = {$this->_oLM->alias}.{$this->_oLM->column}";
    }

    public function joinAsLast($aAttributes)
    {
        return $this->joinLinkedModel();
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

    public function linkedModelTableAlias()
    {
        return $this->_oLM->alias;
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
        if (isset($o->value[1]) && !isset(self::$_aArrayOperators[$o->operator]))
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
        $this->_oCM->pk        = $this->_oCM->t->primaryKey;

        $this->_oLM->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->_oLM->column    = $this->_oLM->t->columnRealName($this->_oLM->attribute);
    }

    public function condition($o)
    {
        static::_checkConditionValidity($o);
        self::arrayfy($o);

        // Get attribute column name.
        if ($o->attribute === 'id')
        {
            $mColumn = $this->_oCM->column;
            $oTable  = $this->_oCM->t;
        }
        else
        {
            $mColumn = $this->_oLM->t->columnRealName($o->attribute);
            $oTable  = $this->_oLM->t;
        }

        $sLHS = self::leftHandSide($mColumn, $oTable, false);
        $sRHS = Condition::rightHandSide($o->value);

        return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
    }

    public function insert($oCM, $oLM)
    {
    }

    public function joinAsLast($aAttributes)
    {
		foreach ($aAttributes as $o)
		{
			if (!$o->attribute === 'id') {
				return $this->joinLinkedModel();
			}
		}
    }

    protected static function _checkConditionValidity($o)
    {
        parent::_checkConditionValidity($o);

        if ($o->logic === 'and' && isset($o->value[1]))
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
        $this->_oCM->pk        = $this->_oCM->t->primaryKey;

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

        $sLHS = Condition::leftHandSide($o->attribute, $this->_oLM->t);
        $sRHS = Condition::rightHandSide($o->value);

        return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
    }

    public function insert($oCM, $oLM)
    {
    }

    protected static function _checkConditionValidity($o)
    {
        parent::_checkConditionValidity($o);

        if ($o->logic === 'and' && isset($o->value[1]))
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
        $this->_oCM->pk        = $this->_oCM->t->primaryKey;

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

        $oLM     = $this->_oLM;
        $oCM     = $this->_oCM;

        if ($o->logic === 'or')
        {
            $sLHS = Condition::leftHandSide($o->attribute, $oLM->t, true, '2');
            $sRHS = Condition::rightHandSide($o->value);

            $sLHS_LMColumn = Condition::leftHandSide($oLM->column, $oLM->t, false, '2');
            $sLHS_CMColumn = Condition::leftHandSide($oCM->column, $oCM->t, false);

            return "EXISTS (
                        SELECT NULL
                        FROM $oLM->table {$oLM->alias}2
                        WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                        AND   {$sLHS} {$o->operator} {$sRHS}
                    )";
        }
        else // logic == 'and'
        {
            $sLHS2 = Condition::leftHandSide($o->attribute, $oLM->t, true, '2');
            $sLHS3 = Condition::leftHandSide($o->attribute, $oLM->t, true, '3');
            $sRHS  = Condition::rightHandSide($o->value);

            $sLHS_LMColumn = Condition::leftHandSide($oLM->column, $oLM->t, false, '3');
            $sLHS_CMColumn = Condition::leftHandSide($oCM->column, $oCM->t, false);

            return "NOT EXISTS (
                        SELECT NULL
                        FROM $oLM->table {$oLM->alias}2
                        WHERE {$sLHS2} {$o->operator} {$sRHS}
                        AND {$sLHS2} NOT IN (
                            SELECT {$sLHS3}
                            FROM $oLM->table {$oLM->alias}3
                            WHERE {$sLHS_LMColumn} = {$sLHS_CMColumn}
                            AND   {$sLHS3}         = {$sLHS2}
                        )
                    )";

                /*
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
                */
                /*
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
                */
        }
    }

    public function insert($oCM, $oLM)
    {
    }

    public function joinAsLast($aAttributes)
    {
		foreach ($aAttributes as $o)
		{
			if ($o->logic !== 'or') {
				return $this->joinLinkedModel();
			}
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
        $this->_oCM->pk        = $this->_oCM->t->primaryKey;

        $sLMClass = $this->_oLM->class;
        $this->_oLM->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->_oLM->column    = $this->_oLM->t->columnRealName($this->_oLM->attribute);

        $this->_oJM = new \StdClass();

        if (isset($a['join_model']))
        {
            $this->_oJM->class = $s = $a['join_model'] . self::$_oConfig->modelClassSuffix;
            $this->_oJM->t     = $s::table();
            $this->_oJM->table = $this->_oJM->t->name;
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

        if ($o->logic === 'or')
        {
            $sRHS = Condition::rightHandSide($o->value);
            if ($o->attribute === 'id')
            {
                $sLHS = Condition::leftHandSide($this->_oJM->to, $this->_oJM->alias, false);
            }
            else
            {
                $sLHS = Condition::leftHandSide($o->attribute, $this->_oLM->t, false);
            }

            return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
        }
        else // $o->logic === 'and'
        {
            $sLHS = Condition::leftHandSide($o->attribute, $this->_oLM->t, false);
            $sRHS = Condition::rightHandSide($o->value);

            $sCond_JMFrom  = Condition::leftHandSide($this->_oJM->from,   $this->_oJM->alias, false);
            $sCond_JMFrom2 = Condition::leftHandSide($this->_oJM->from,   $this->_oJM->alias, false, '2');
            $sCond_JMTo2   = Condition::leftHandSide($this->_oJM->to,     $this->_oJM->alias, false, '2');
            $sCond_LM2     = Condition::leftHandSide($this->_oLM->column, $this->_oLM->alias, false, '2');

            return "NOT EXISTS (
                        SELECT NULL
                        FROM {$this->_oLM->table} {$this->_oLM->alias}2
                        WHERE {$sLHS} {$o->operator} {$sRHS}
                        AND NOT EXISTS (
                            SELECT NULL
                            FROM {$this->_oJM->table} {$this->_oJM->alias}2
                            WHERE {$sCond_JMFrom} = {$sCond_JMFrom2}
                            AND   {$sCond_JMTo2}  = {$sCond_LM2}
                        )
                    )";

                /*
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
                */
        }
    }

    public function deleteJoinModel($mValue)
    {
        $oQuery = Query::delete(array($this->_oJM->from => $mValue), $this->_oJM->table);
        $oQuery->run();
    }

    public function deleteLinkedModel($mValue)
    {
        $sLHS = Condition::leftHandSide($this->_oLM->pk, 'a', false);
        $sRHS = Condition::leftHandSide($this->_oJM->to, 'b', false);
        $sCondition = $sLHS . ' = ' . $sRHS;

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

    public function joinLinkedModel()
    {
        return $this->_joinJM() . ' ' .  $this->_joinLM();
    }

    public function joinAsLast($aAttributes)
    {
        $sRes = '';

        // We always want to join the middle table.
        $sRes .= $this->_joinJM();

		foreach ($aAttributes as $o)
		{
			// And, under certain conditions, the linked table.
			if (!$o->logic === 'or' || !$o->attribute === 'id')
			{
				$sRes .= $this->_joinLM();
				break;
			}
		}

        return $sRes;
    }

    public function joinTableAlias()
    {
        return $this->_oJM->alias;
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

    private function _joinJM()
    {
		return " JOIN {$this->_oJM->table} {$this->_oJM->alias} ON {$this->_oCM->alias}.{$this->_oCM->column} = {$this->_oJM->alias}.{$this->_oJM->from}";
    }

    private function _joinLM()
    {
		return " JOIN {$this->_oLM->table} {$this->_oLM->alias} ON {$this->_oJM->alias}.{$this->_oJM->to} = {$this->_oLM->alias}.{$this->_oLM->pk}";
    }

}
