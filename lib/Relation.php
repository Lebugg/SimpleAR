<?php namespace SimpleAR;
/**
 * This file contains the Relation class and its subclasses.
 *
 * @author Lebugg
 */

require __DIR__ . '/Relation/BelongsTo.php';
require __DIR__ . '/Relation/HasOne.php';
require __DIR__ . '/Relation/HasMany.php';
require __DIR__ . '/Relation/ManyMany.php';

use \SimpleAR\Facades\Cfg;
use \SimpleAR\Facades\DB;

/**
 * This class modelizes a Relation between two Models.
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
abstract class Relation
{

    /**
     * The relation name.
     *
     * It corresponds to the key in `Model::$_relations`.
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
     * Current Model is the Model that defines this Relation.
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
     *      protected $_relations = array(
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
     *      protected $_relations = array(
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
     *      protected $_filters = array(
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
     *      protected $_relations = array(
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
     *      protected $_relations = array(
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
    private static $_typeToClass = array(
        'belongs_to' => 'BelongsTo',
        'has_one'    => 'HasOne',
        'has_many'   => 'HasMany',
        'many_many'  => 'ManyMany',
    );

    /**
     * Constructor.
     *
     * @param array  $a         The relation definition array define in the current model.
     * @param string $cmClass  The Current Model (CM). This is the Model that defines the relation.
     */
    protected function __construct($a, $cmClass)
    {
        $this->cm = new \StdClass();
        $this->cm->class     = $cmClass;
        $this->cm->t         = $cmClass::table();
        $this->cm->table     = $this->cm->t->name;
        $this->cm->alias     = $this->cm->t->alias;

        $this->lm = new \StdClass();
        $this->lm->class = $s = $a['model'] . Cfg::get('modelClassSuffix');
        $this->lm->t     = $s::table();
        $this->lm->table = $this->lm->t->name;
        $this->lm->alias = $this->lm->t->alias;
        $this->lm->pk    = $this->lm->t->primaryKey;

        if (isset($a['on_delete_cascade'])) { $this->onDeleteCascade = $a['on_delete_cascade']; }
        if (isset($a['filter']))            { $this->filter          = $a['filter']; }
		if (isset($a['conditions']))		{ $this->conditions      = $a['conditions']; }
		if (isset($a['order']))				{ $this->order           = $a['order']; }
    }

    public function deleteLinkedModel($value)
    {
        $query = new Query\Delete($this->lm->class);
        $query->conditions(array($this->lm->attribute => $value))
            ->run();
    }

    /**
     * Create a Relation instance.
     *
     * @param string $name The name of the relation.
     * @param array  $config The relation config array defined in
     * static::$_relations.
     * @param string $cmClass The current model class.
     *
     * @return Relation
     */
    public static function forge($name, $config, $cmClass)
    {
        $relationClass = 'SimpleAR\\Relation\\' . self::$_typeToClass[$config['type']];

        $relation = new $relationClass($config, $cmClass);
        $relation->name = $name;

        return $relation;
    }

    public function joinLinkedModel($cmAlias, $lmAlias, $joinType)
    {
		return $this->_buildJoin($joinType, $cmAlias, $this->lm->table, $lmAlias, $this->cm->column, $this->lm->column);
    }

    public function joinAsLast($conditions, $cmAlias, $lmAlias, $joinType)
    {
        return $this->joinLinkedModel($cmAlias, $lmAlias, $joinType);
    }

    protected function _buildJoin($joinType, $aliasA, $tableB, $aliasB, $colA, $colB)
    {
        $qAliasA = DB::quote($aliasA); $qColA   = DB::quote($colA);
        $qTableB = DB::quote($tableB); $qAliasB = DB::quote($aliasB); $qColB   = DB::quote($colB);

		return ' ' . $joinType . ' JOIN ' . $qTableB . ' ' . $qAliasB
            . ' ON (' . $qAliasA . '.' . $qColA . ' = ' .  $qAliasB . '.' .  $qColB . ')';
    }
}
