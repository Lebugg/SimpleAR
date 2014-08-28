<?php namespace SimpleAR\Orm;
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
     * Relation scope.
     *
     * @var array
     */
    protected $_scope;

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
	public $order = array();

    /**
     * Defines a filter to apply to linked model instances.
     *
     * If the relation defines a filter, it will be apply to linked model instances. This allows you
     * to retrieve only needed information instead of a bunch of useless data.
     *
     * To specify an order for a relation, just add an "order_by" entry in the relation array.
     *
     * Example:
     *  ```php
     *  class Person
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
     *              'model' => 'Person',
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
     * True if cardinality is *-to-many, false otherwise.
     *
     * @see isToMany() Getter.
     *
     * @var bool
     */
    protected $_toMany = false;

    /**
     * Constructor.
     *
     * @param array  $info The relation definition array define in the current model.
     * @param string $cmClass The Current Model (CM). This is the Model that defines the relation.
     */
    public function __construct(array $info = array(), $cmClass = '')
    {
        $cmClass && $this->setCmClass($cmClass);
        $info    && $this->setInformation($info);
    }

    public function setCmClass($cmClass)
    {
        $this->cm = new \StdClass();
        $this->cm->class     = $cmClass;
        $this->cm->t         = $cmClass::table();
        $this->cm->table     = $this->cm->t->name;
    }

    public function setInformation(array $info)
    {
        $this->lm = new \StdClass();
        $this->lm->class = $s = $info['model'] . Cfg::get('modelClassSuffix');
        $this->lm->t     = $s::table();
        $this->lm->table = $this->lm->t->name;
        //$this->lm->pk    = $this->lm->t->primaryKey;

        foreach (array('filter', 'conditions', 'order') as $item)
        {
            if (isset($info[$item]))
            {
                $this->$item = $info[$item];
            }
        }

        if (! empty($info['scope']))
        {
            $this->setScope($info['scope']);
        }
    }

    /**
     * Get relation scope.
     *
     * @return array
     */
    public function getScope()
    {
        return $this->_scope;
    }

    /**
     * Set scope for a relation.
     *
     * Scopes are like conditions, but use query builder to construct a query.
     * We separate them from `conditions` entry in order to handle them more 
     * easily.
     *
     * Scope entry can take different forms:
     *  * it can be a simple string (for one scope only).
     *  * it can be an array of scopes. This array can mix both numeric entry 
     *  and associative entry.
     *      * former case: value is a scope name;
     *      * latter case: key is a scope name; value is an array of arguments 
     *      to pass to the scope function.
     *
     * This function standardizes the parameter to produce associative array as 
     * described just above.
     *
     * @param string|array $scope The scope to set.
     */
    public function setScope($scope)
    {
        $res = array();

        foreach ((array) $scope as $key => $value)
        {
            if (is_numeric($key))
            {
                list($key, $value) = array($value, array());
            }

            $res[$key] = (array) $value;
        }

        $this->_scope = $res;
    }

    /**
     * Is the cardinality *-to-many?
     *
     * @return bool True if the cardinality is *-to-many, false otherwise.
     *
     * @see $_toMany
     */
    public function isToMany()
    {
        return $this->_toMany;
    }

    // public function deleteLinkedModel($value)
    // {
    //     $query = new Query\Delete($this->lm->class);
    //     $query->conditions(array($this->lm->attribute => $value))
    //         ->run();
    // }
    //
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
        $relationClass = 'SimpleAR\Orm\Relation\\' . self::$_typeToClass[$config['type']];

        $relation = new $relationClass($config, $cmClass);
        $relation->name = $name;

        return $relation;
    }

    /**
     * Return an associative list of attributes on which the relation is made.
     *
     * Examples:
     * ---------
     *
     * If relation is made on `fk_id` for $cm and on `id` for $lm. The result of 
     * will be the following array:
     *
     *  `['fk_id' => 'id']`
     *
     * If relation is made on `key_one` and `key_to` for $cm and respectively on 
     * `pk_A` and `pk_B` for $lm, result will be:
     *
     *  `['key_one' => 'pk_A', 'key_two' => 'pk_B']`
     *
     */
    public function getJoinAttributes()
    {
        $cms = $this->cm->attribute; $cms = is_array($cms) ? $cms : array($cms);
        $lms = $this->lm->attribute; $lms = is_array($lms) ? $lms : array($lms);

        $res = array();
        foreach ($cms as $i => $cmCol)
        {
            $res[$cmCol] = $lms[$i];
        }

        return $res;
    }

    /**
     * Return an associative list of columns on which the relation is made.
     *
     * Examples:
     * ---------
     *
     * If relation is made on `fk_id` for $cm and on `id` for $lm. The result of 
     * will be the following array:
     *
     *  `['fk_id' => 'id']`
     *
     * If relation is made on `key_one` and `key_to` for $cm and respectively on 
     * `pk_A` and `pk_B` for $lm, result will be:
     *
     *  `['key_one' => 'pk_A', 'key_two' => 'pk_B']`
     *
     */
    public function getJoinColumns()
    {
        $cms = $this->cm->column; $cms = is_array($cms) ? $cms : array($cms);
        $lms = $this->lm->column; $lms = is_array($lms) ? $lms : array($lms);

        $res = array();
        foreach ($cms as $i => $cmCol)
        {
            $res[$cmCol] = $lms[$i];
        }

        return $res;
    }

    /**
     * Return current model attributes involved in relation.
     *
     * @return array
     */
    public function getCmAttributes()
    {
        return (array) $this->cm->attribute;
    }

    /**
     * Return linked model attributes involved in relation.
     *
     * @return array
     */
    public function getLmAttributes()
    {
        return (array) $this->lm->attribute;
    }

    /**
     * Return the order by option of this relation.
     *
     * @return array|null
     */
    public function getOrderBy()
    {
        return $this->order;
    }
}
