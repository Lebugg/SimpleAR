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

    public $lm;
    public $cm;

    public $onDeleteCascade = false;
    public $filter          = null;
    public $name;
	public $conditions = array();
	public $order      = array();

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

        $this->cm = new \StdClass();
        $this->cm->class     = $sCMClass;
        $this->cm->t         = $sCMClass::table();
        $this->cm->table     = $this->cm->t->name;
        $this->cm->alias     = $this->cm->t->alias;

        $this->lm = new \StdClass();
        $this->lm->class = $s = $a['model'] . self::$_oConfig->modelClassSuffix;
        $this->lm->t     = $s::table();
        $this->lm->table = $this->lm->t->name;
        $this->lm->alias = $this->lm->t->alias;
        $this->lm->pk    = $this->lm->t->primaryKey;

        if (isset($a['on_delete_cascade'])) { $this->onDeleteCascade = $a['on_delete_cascade']; }
        if (isset($a['filter']))            { $this->filter          = $a['filter']; }
		if (isset($a['conditions']))		{ $this->conditions      = $a['conditions']; }
		if (isset($a['order']))				{ $this->order           = $a['order']; }
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

    public static function construct($sName, $a, $sCMClass)
    {
        $s = 'SimpleAR\\' . self::$_aTypeToClass[$a['type']];

        $o = new $s($a, $sCMClass);
        $o->name = $sName;

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
        $oQuery = Query::delete(array($this->lm->attribute => $mValue), $this->lm->class);
        $oQuery->run();
    }

    public abstract function insert($oCM, $oLM);

    public function joinLinkedModel($iDepth)
    {
		return " JOIN {$this->lm->table} {$this->lm->alias} ON {$this->cm->alias}.{$this->cm->column} = {$this->lm->alias}.{$this->lm->column}";
    }

    public function joinAsLast($aAttributes, $iDepth)
    {
        return $this->joinLinkedModel();
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

        $this->cm->attribute = isset($a['key_from'])
            ? $a['key_from']
            : strtolower($this->lm->t->modelBaseName) . self::$_oConfig->foreignKeySuffix;
            ;

        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
    }

    public function condition($o)
    {
        static::_checkConditionValidity($o);
        self::arrayfy($o);

        // Get attribute column name.
        if ($o->attribute === 'id')
        {
            $mColumn = $this->cm->column;
            $oTable  = $this->cm->t;
        }
        else
        {
            $mColumn = $this->lm->t->columnRealName($o->attribute);
            $oTable  = $this->lm->t;
        }

        $sLHS = self::leftHandSide($mColumn, $oTable, false);
        $sRHS = Condition::rightHandSide($o->value);

        return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
    }

    public function insert($oCM, $oLM)
    {
    }

    public function joinAsLast($aAttributes, $iDepth)
    {
		foreach ($aAttributes as $o)
		{
			if ($o->attribute !== 'id') {
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

        $this->cm->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($a['key_to'])
            ? $a['key_to']
            : strtolower($this->cm->t->modelBaseName) . self::$_oConfig->foreignKeySuffix
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
    }

    public function condition($o)
    {
        static::_checkConditionValidity($o);
        self::arrayfy($o);

        $sLHS = Condition::leftHandSide($o->attribute, $this->lm->t);
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
        
        $this->cm->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = (isset($a['key_to']))
            ? $a['key_to']
            : strtolower($this->cm->t->modelBaseName) . self::$_oConfig->foreignKeySuffix
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
    }

    public function condition($o)
    {
        self::_checkConditionValidity($o);
        self::arrayfy($o);

        $oLM     = $this->lm;
        $oCM     = $this->cm;

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

    public function joinAsLast($aAttributes, $iDepth)
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
    public $jm;

    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);

        $this->cm->attribute = isset($a['key_from']) ? $a['key_from'] : 'id';
        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
        $this->lm->pk        = $this->lm->t->primaryKey;

        $this->jm = new \StdClass();

        if (isset($a['join_model']))
        {
            $this->jm->class = $s = $a['join_model'] . self::$_oConfig->modelClassSuffix;
            $this->jm->t     = $s::table();
            $this->jm->table = $this->jm->t->name;
            $this->jm->from  = $this->jm->t->columnRealName(isset($a['join_from']) ? $a['join_from'] : strtolower($this->cm->t->modelBaseName) . self::$_oConfig->foreignKeySuffix);
            $this->jm->to    = $this->jm->t->columnRealName(isset($a['join_to'])   ? $a['join_to']   : strtolower($this->lm->t->modelBaseName) . self::$_oConfig->foreignKeySuffix);
        }
        else
        {
            $this->jm->class = null;
            $this->jm->table = isset($a['join_table']) ? $a['join_table'] : $this->cm->table . '_' . $this->lm->table;
            $this->jm->from  = isset($a['join_from'])  ? $a['join_from']  : $this->cm->pk;
            $this->jm->to    = isset($a['join_to'])    ? $a['join_to']    : $this->lm->pk;
        }

        $this->jm->alias = strtolower($this->jm->table);
    }

    public function condition($o)
    {
        self::_checkConditionValidity($o);
        self::arrayfy($o);


		$mColumn = $this->lm->t->columnRealName($o->attribute);

        if ($o->logic === 'or')
        {
            $sRHS = Condition::rightHandSide($o->value);
            if ($o->attribute === 'id')
            {
                $sLHS = Condition::leftHandSide($this->jm->to, $this->jm->alias, false);
            }
            else
            {
                $sLHS = Condition::leftHandSide($o->attribute, $this->lm->t, true);
            }

            return $sLHS . ' ' . $o->operator . ' ' . $sRHS;
        }
        else // $o->logic === 'and'
        {
            $sLHS = Condition::leftHandSide($o->attribute, $this->lm->t, false);
            $sRHS = Condition::rightHandSide($o->value);

            $sCond_JMFrom  = Condition::leftHandSide($this->jm->from,   $this->jm->alias, false);
            $sCond_JMFrom2 = Condition::leftHandSide($this->jm->from,   $this->jm->alias, false, '2');
            $sCond_JMTo2   = Condition::leftHandSide($this->jm->to,     $this->jm->alias, false, '2');
            $sCond_LM2     = Condition::leftHandSide($this->lm->column, $this->lm->alias, false, '2');

            return "NOT EXISTS (
                        SELECT NULL
                        FROM {$this->lm->table} {$this->lm->alias}2
                        WHERE {$sLHS} {$o->operator} {$sRHS}
                        AND NOT EXISTS (
                            SELECT NULL
                            FROM {$this->jm->table} {$this->jm->alias}2
                            WHERE {$sCond_JMFrom} = {$sCond_JMFrom2}
                            AND   {$sCond_JMTo2}  = {$sCond_LM2}
                        )
                    )";

                /*
                return "NOT EXISTS (
                            SELECT NULL
                            FROM {$this->lm->table} {$this->lm->alias}2
                            WHERE {$this->lm->alias}2.{$mColumn} $o->operator $sRightHandSide
                            AND NOT EXISTS (
                                SELECT NULL
                                FROM {$this->jm->table} {$this->jm->alias}2
                                WHERE {$this->jm->alias}.{$this->jm->from} = {$this->jm->alias}2.{$this->jm->from}
                                AND   {$this->jm->alias}2.{$this->jm->to}  = {$this->lm->alias}2.{$this->lm->column}
                            )
                        )";
                */
        }
    }

    public function deleteJoinModel($mValue)
    {
        $oQuery = Query::delete(array($this->jm->from => $mValue), $this->jm->table);
        $oQuery->run();
    }

    public function deleteLinkedModel($mValue)
    {
        $sLHS = Condition::leftHandSide($this->lm->pk, 'a', false);
        $sRHS = Condition::leftHandSide($this->jm->to, 'b', false);
        $sCondition = $sLHS . ' = ' . $sRHS;

        $sQuery =  "DELETE FROM {$this->lm->table} a
                    WHERE EXISTS (
                        SELECT NULL
                        FROM {$this->jm->table} b
                        WHERE b." . implode(' = ? AND b.', $this->jm->from) ." = ?
                        AND $sCondition
                    )";

        self::$_oDb->query($sQuery, $mValue);
    }

    public function insert($oCM, $oLM)
    {
        $sQuery = 'INSERT INTO ' . $this->jm->table . ' (' . $this->jm->from . ',' . $this->jm->to . ') VALUES (?,?)';
        try {
            self::$_oDb->query($sQuery, array($oCM->{$this->cm->attribute}, $oLM->{$this->lm->attribute}));
        } catch(Exception $oEx) {
            throw new DuplicateKeyException(get_class($oCM) . '(' . $oCM->id . ') is already linked to ' . get_class($oLM) . '(' . $oLM->id . ').');
        }
    }

    public function joinLinkedModel($iDepth)
    {
        return $this->_joinJM() . ' ' .  $this->_joinLM();
    }

    public function joinAsLast($aAttributes, $iDepth)
    {
        $sRes = '';

        // We always want to join the middle table.
        $sRes .= $this->_joinJM();

		foreach ($aAttributes as $o)
		{
			// And, under certain conditions, the linked table.
			if ($o->logic !== 'or' || $o->attribute !== 'id')
			{
				$sRes .= $this->_joinLM();
				break;
			}
		}

        return $sRes;
    }

	public function reverse()
	{
		$oRelation = clone $this;

		$oRelation->name = $this->name . '_r';

		$oRelation->cm  = clone $this->lm;
		$oRelation->lm  = clone $this->cm;

        $oRelation->jm       = clone $this->jm;
		$oRelation->jm->from = $this->jm->to;
		$oRelation->jm->to   = $this->jm->from;

        return $oRelation;
	}

    private function _joinJM()
    {
		return " JOIN {$this->jm->table} {$this->jm->alias} ON {$this->cm->alias}.{$this->cm->column} = {$this->jm->alias}.{$this->jm->from}";
    }

    private function _joinLM()
    {
		return " JOIN {$this->lm->table} {$this->lm->alias} ON {$this->jm->alias}.{$this->jm->to} = {$this->lm->alias}.{$this->lm->pk}";
    }

}
