<?php
/**
 * This file contains the Relationship class and its subclasses.
 *
 * @author Lebugg
 */
namespace SimpleAR;

/**
 * This class modelizes a Relationship between two Models.
 *
 * There are four different possible relations: BelongsTo, HasOne, HasMany and ManyMany.
 *
 * Examples:
 * ---------
 *
 * * BelongsTo:
 * A Beer is brewed by a Brewery. In database, you would have a `brewery_id` in `beer` table. So a
 * Beer *belongs to* a Brewery.
 * 
 * * HasMany
 * At the opposite, a Brewery brews one or several beers, maybe thousands (want to go there !).
 * So, a Brewery *has many* Beers.
 *
 * * HasOne
 * Someone owns the Brewery, let's say, the BrewerInChief. According to where you want to put the
 * dependancy relation, you have a different relationship. If in `brewery` table, you define a
 * `owner_id`, then the Brewery *belongs to* the BrewerInChief. Conversely, if you define a
 * `brewery_id` in the `brewersinchief` table, then a Brewery *has one* BrewerInChief and the
 * BrewerInChief *belongs to* a Brewery.
 * 
 * * ManyMany
 * A beer can be drunk by several drinkers and drinkers can drink several different beers (Sure!).
 * So, we end with a *many to many* relationship.
 */
abstract class Relationship
{
    protected static $_oDb;
    protected static $_cfg;

    /**
     * The relation name.
     *
     * It corresponds to the key in `Model::$_aRelations`.
     *
     * @var string
     */
    public $name;

    /**
     * Contains information about linked model (lm).
     *
     * @var object
     */
    public $lm;

    /**
     * Contains information about current model (cm).
     *
     * Current Model is the Model that defines this Relationship.
     *
     * @var object
     */
    public $cm;

    /**
     * Conditions specified for this relation.
     *
     * If the relation defines some conditions, only linked model instances that verify these
     * conditions will be associated by this relation.
     *
     * To specify conditions for a relation, just add a "conditions" entry in the relation array.
     * Example:
     *  ```php
     *  class Zoo
     *  {
     *      protected $_aRelations = array(
     *          'birds' => array(
     *              'type'  => 'has_many',
     *              'model' => 'Animal',
     *
     *              'conditions' => array('hasWings' => true), // Will only retrieve Animal that
     *                                                         // have wings (birds).
     *          ),
     *      );
     *  }
     *  ```
     *
     * @var array
     */
	public $conditions = array();

    /**
     * Defines a sort order for linked model instances.
     *
     * If the relation defines an order, linked model instances will be sorted with this order. It
     * just adds an ORDER BY clause to the SQL query.
     *
     * To specify an order for a relation, just add an "order_by" entry in the relation array.
     * Example:
     *  ```php
     *  class School
     *  {
     *      protected $_aRelations = array(
     *          'students' => array(
     *              'type'  => 'has_many',
     *              'model' => 'Student',
     *
     *              'order_by' => array('lastName', 'firstName'), // Students will be ordered by
     *                                                            // last name, then first name.
     *          ),
     *      );
     *  }
     *  ```
     * @var string|array
     */
	public $order;

    /**
     * Defines a filter to apply to linked model instances.
     *
     * If the relation defines a filter, it will be apply to linked model instances. This allows you
     * to retrieve only needed information instead of a bunch of useless data.
     *
     * To specify an order for a relation, just add an "order_by" entry in the relation array.
     * Example:
     *  ```php
     *  // Person with information retrieved by NSA.
     *  class NSA_Person
     *  {
     *      protected $_aFilters = array(
     *          'restricted' => array(
     *              'age',
     *              'firstName',
     *              'lastName',
     *          ),
     *      );
     *  }
     *
     *  class Company
     *  {
     *      protected $_aRelations = array(
     *          'workers' => array(
     *              'type'  => 'has_many',
     *              'model' => 'NSA_Person',
     *
     *              'filter' => 'restricted',
     *          ),
     *      );
     *  }
     *  ```
     *
     * @var string
     */
    public $filter;

    /**
     * Does linked models instances have to be deleted on cascade?
     *
     * @note This parameter is only used when `Config::$_doForeignKeyWork` is set to true.
     *
     * Use "on_delete_cascade" entry in relation definition array to set this parameter.
     *
     * @var bool
     */
    public $onDeleteCascade = false;

    /**
     * This array does the translation between relation-entry like relation type and matching class
     * name.
     *
     * To define the type of relation you want use the "type" entry in the relation definition
     * array.
     *
     * Example:
     * --------
     *  ```php
     *  class Monkey
     *  {
     *      protected $_aRelations = array(
     *          // A monkey can have several children.
     *          'children' => array(
     *              'type'  => 'has_many',
     *              'model' => 'Monkey',
     *          ),
     *          // Fruits this monkey eats.
     *          'eatenFruits' => array(
     *              'type'       => 'many_many',
     *              'model'      => 'Fruit',
     *              'table_join' => 'EATS'
     *          ),
     *          // Forest where this monkey lives.
     *          'forest' => array(
     *              'type'  => 'belongs_to',
     *              'model' => 'Forest',
     *          ),
     *          // His wife.
     *          'wife' => array(
     *              'type'  => 'has_one',
     *              'model' => 'Monkey',
     *          ),
     *      );
     *  }
     *  ```
     *
     *  @var array
     */
    private static $_aTypeToClass = array(
        'belongs_to' => 'BelongsTo',
        'has_one'    => 'HasOne',
        'has_many'   => 'HasMany',
        'many_many'  => 'ManyMany',
    );

    /**
     * Stores the model class suffix defined by `Config::$_modelClassSuffix`
     *
     * @see Config::$_modelClassSuffix
     *
     * @var string
     */
    protected static $_modelClassSuffix;

    /**
     * Stores the model class suffix defined by `Config::$_foreignKeySuffix`
     *
     * @see Config::$_foreignKeySuffix
     *
     * @var string
     */
    protected static $_sForeignKeySuffix;

    /**
     * Constructor.
     *
     * @param array  $a         The relation definition array define in the current model.
     * @param string $sCMClass  The Current Model (CM). This is the Model that defines the relation.
     */
    protected function __construct($a, $sCMClass)
    {
        $this->cm = new \StdClass();
        $this->cm->class     = $sCMClass;
        $this->cm->t         = $sCMClass::table();
        $this->cm->table     = $this->cm->t->name;
        $this->cm->alias     = $this->cm->t->alias;

        $this->lm = new \StdClass();
        $this->lm->class = $s = $a['model'] . self::$_modelClassSuffix;
        $this->lm->t     = $s::table();
        $this->lm->table = $this->lm->t->name;
        $this->lm->alias = $this->lm->t->alias;
        $this->lm->pk    = $this->lm->t->primaryKey;

        if (isset($a['on_delete_cascade'])) { $this->onDeleteCascade = $a['on_delete_cascade']; }
        if (isset($a['filter']))            { $this->filter          = $a['filter']; }
		if (isset($a['conditions']))		{ $this->conditions      = $a['conditions']; }
		if (isset($a['order']))				{ $this->order           = $a['order']; }
    }

    public function deleteLinkedModel($mValue)
    {
        $oQuery = Query::delete(array($this->lm->attribute => $mValue), $this->lm->class);
        $oQuery->run();
    }

    public static function forge($sName, $a, $sCMClass)
    {
        $s = 'SimpleAR\\' . self::$_aTypeToClass[$a['type']];

        $o = new $s($a, $sCMClass);
        $o->name = $sName;

        return $o;
    }

    public static function init($oConfig, $oDatabase)
    {
        self::$_modelClassSuffix = $oConfig->modelClassSuffix;
        self::$_sForeignKeySuffix = $oConfig->foreignKeySuffix;

        self::$_oDb  = $oDatabase;
        self::$_cfg = $oConfig;
    }

    public function joinLinkedModel($depth, $joinType)
    {
        $previousDepth = $depth <= 1 ? '' : $depth - 1;
        $depth         = $depth ?: '';

		return " $joinType JOIN `{$this->lm->table}` {$this->lm->alias}$depth ON {$this->cm->alias}$previousDepth.{$this->cm->column} = {$this->lm->alias}$depth.{$this->lm->column}";
    }

    public function joinAsLast($conditions, $depth, $joinType)
    {
        return $this->joinLinkedModel($depth, $joinType);
    }
}

class BelongsTo extends Relationship
{
    protected function __construct($a, $sCMClass)
    {
        parent::__construct($a, $sCMClass);

        $this->cm->attribute = isset($a['key_from'])
            ? $a['key_from']
            : call_user_func(self::$_cfg->buildForeignKey, $this->lm->t->modelBaseName);
            ;

        $this->cm->column    = $this->cm->t->columnRealName($this->cm->attribute);
        $this->cm->pk        = $this->cm->t->primaryKey;

        $this->lm->attribute = isset($a['key_to']) ? $a['key_to'] : 'id';
        $this->lm->column    = $this->lm->t->columnRealName($this->lm->attribute);
    }


    public function joinAsLast($conditions, $depth, $joinType)
    {
		foreach ($conditions as $condition)
		{
            foreach ($condition->attributes as $a)
            {
                if ($a->name !== 'id') {
                    return $this->joinLinkedModel($depth, $joinType);
                }
            }
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
            : call_user_func(self::$_cfg->buildForeignKey, $this->cm->t->modelBaseName);
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
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
            : call_user_func(self::$_cfg->buildForeignKey, $this->cm->t->modelBaseName);
            ;

        $this->lm->column = $this->lm->t->columnRealName($this->lm->attribute);
    }

    public function joinAsLast($conditions, $depth, $joinType)
    {
		foreach ($conditions as $condition)
		{
            foreach ($condition->attributes as $a)
            {
                if ($a->logic !== 'or') {
                    return $this->joinLinkedModel($depth, $joinType);
                }
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
            $this->jm->class = $s = $a['join_model'] . self::$_modelClassSuffix;
            $this->jm->t     = $s::table();
            $this->jm->table = $this->jm->t->name;
            $this->jm->from  = $this->jm->t->columnRealName(isset($a['join_from']) ? $a['join_from'] : call_user_func(self::$_cfg->buildForeignKey, $this->cm->t->modelBaseName));
            $this->jm->to    = $this->jm->t->columnRealName(isset($a['join_to'])   ? $a['join_to']   : call_user_func(self::$_cfg->buildForeignKey, $this->lm->t->modelBaseName));
        }
        else
        {
            $this->jm->class = null;
            $this->jm->table = isset($a['join_table']) ? $a['join_table'] : $this->cm->table . '_' . $this->lm->table;
            $this->jm->from  = isset($a['join_from'])  ? $a['join_from']  : (strtolower($this->cm->t->name) . '_id');
            $this->jm->to    = isset($a['join_to'])    ? $a['join_to']    : (strtolower($this->lm->t->name) . '_id');
        }

        $this->jm->alias = '_' . strtolower($this->jm->table);
    }

    public function deleteJoinModel($mValue)
    {
        $oQuery = Query::delete(array($this->jm->from => $mValue), $this->jm->table);
        $oQuery->run();
    }

    public function deleteLinkedModel($mValue)
    {
        $sLHS = Condition::leftHandSide($this->lm->pk, 'a');
        $sRHS = Condition::leftHandSide($this->jm->to, 'b');
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

    public function joinLinkedModel($depth, $joinType)
    {
        return $this->_joinJM($depth, $joinType) . ' ' .  $this->_joinLM($depth, $joinType);
    }

    public function joinAsLast($conditions, $depth, $joinType)
    {
        $sRes = '';

       // We always want to join the middle table.
        $sRes .= $this->_joinJM($depth, $joinType);

		foreach ($conditions as $condition)
		{
            foreach ($condition->attributes as $a)
            {
                // And, under certain conditions, the linked table.
                if ($a->logic !== 'or' || $a->name !== 'id')
                {
                    $sRes .= $this->_joinLM($depth, $joinType);
                    break;
                }
            }
		}

        return $sRes;
    }

	public function reverse()
	{
		$relation = clone $this;

		$relation->name = $this->name . '_r';

		$relation->cm  = clone $this->lm;
		$relation->lm  = clone $this->cm;

        $relation->jm       = clone $this->jm;
		$relation->jm->from = $this->jm->to;
		$relation->jm->to   = $this->jm->from;

        return $relation;
	}

    private function _joinJM($depth, $joinType)
    {
        $previousDepth = $depth <= 1 ? '' : $depth - 1;
        $depth         = $depth ?: '';

		return " $joinType JOIN `{$this->jm->table}` {$this->jm->alias}$depth ON {$this->cm->alias}$previousDepth.{$this->cm->column} = {$this->jm->alias}$depth.{$this->jm->from}";
    }

    private function _joinLM($depth, $joinType)
    {
		return " $joinType JOIN `{$this->lm->table}` {$this->lm->alias}$depth ON {$this->jm->alias}$depth.{$this->jm->to} = {$this->lm->alias}$depth.{$this->lm->pk}";
    }

}
