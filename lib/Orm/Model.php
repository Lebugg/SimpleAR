<?php namespace SimpleAR\Orm;
/**
 * This file contains the Model class.
 *
 * @author Damien Launay
 */

use \SimpleAR\Orm\Table;
use \SimpleAR\Orm\Builder as QueryBuilder;

use \SimpleAR\Facades\DB;
use \SimpleAR\Facades\Cfg;

use \SimpleAR\Exception\Database as DatabaseEx;
use \SimpleAR\Exception\RecordNotFound;
use \SimpleAR\Exception;

/**
 * This class is the super class of all models.
 * It defines some basic functions shared by all models.
 *
 * @author Damien Launay
 */
abstract class Model
{

    /**
     * The name of the database table this model is associated with.
     *
     * @var string
     */
    protected static $_tableName;

    /**
     * The primary key name of the model.
     *
     * It can have two types:
     *
     *  * If a string, it *must* correspond to a valid field of the table. This
     *  field *should* have these properties:
     *
     *      * unique;
     *      * integer;
     *      * auto-increment.
     *
     *  * If an array, it must contain only model attribute names.
     *
     * @var string|array
     */
    protected static $_primaryKey;

    /**
     * This array contains associations between class members and
     * database fields.
     *
     * Advantages of this array are multiple:
     *
     *  * DB field names abstraction (some of them are meaningless) ;
     *  * Defines which fields will be retrieved (some of them are useless) ;
     *
     * An inconvenient is that it is necessary to declare a class member
     * corresponding to $_columns keys.
     *
     * The array is constructed this way:
     * ```php
     *  array(
     *      'myAttribute' => 'db_field_name_for_my_attribute',
     *      ...
     *  );
     * ```
     *
     * It is possible to specify a default value for attribute by using the
     * {@see \SimpleAR\Model::$_defaultValues} array. These value
     * will be used for model instance insertion in DB.
     *
     * @var array
     */
    protected static $_columns = array();

    /**
     * Attributes' default values array.
     *
     * Example:
     * ```php
     *  protected $_defaultValues = array(
     *      'myAttribute' => 12,
     *  );
     * ```
     * If nothing is set for 'myAttribute' before saving, value 12 will be
     * inserted.
     *
     * @var array
     */
    protected static $_defaultValues = array();

    /**
     * This array contains the list of filters defined for the model.
     *
     * Filters help you to manage data you want to render. It is useful to
     * choose only relevent data according to the user, the option specified in
     * the URL.
     *
     * Here is the syntax of this array:
     * ```php
     * array (
     *      '<filter's name>' => array(
     *          'field1',
     *          'field2',
     *          ...
     *      ),
     *      '<second filter's name>' => array(
     *      ...
     *      ),
     *      ...
     * );
     * ```
     *
     * <filter's name> is an arbitrary name you choose for the filter. field1,
     * field2 are model's attributes (that is to say, key of
     * \SimpleAR\Model::$_columns).
     */
    protected static $_filters = array();

    /**
     * Accessible attributes for the current object.
     *
     * It is set when using a filter on the current instance.
     *
     * @var array
     */
    protected $_whitelist = array();

    /**
     * An array that allows another way to filter output.
     *
     * All fields present in this array will be excluded from the output. A
     * difference operation is made between this array and $_columns
     * before output.
     *
     * You can use exclude() function to add fields to exclude from output.
     *
     * @var array
     */
    protected static $_excludedKeys = array();

    protected static $_includedKeys = array();

    /**
     * This array contains arrays of constraints that must be checked
     * before inserting a row in the database.
     *
     * This is very useful to check constraints that are not defined in the
     * database. Of course, it takes additional request to perform, but database
     * integrity is worth it.
     *
     * It is constructed this way:
     * ```php
     *  array(
     *      array(
     *          'myAttribute1',
     *          'myAttribute2',
     *      ),
     *      array(
     *          'myAttribute3',
     *      ),
     *      ...
     *  );
     * ```
     *
     * In the above example, it will check (before insert) that couple
     * ('myAttribute1', 'myAttribute2') will still satisfy unicity constraint
     * after model insertion. Then, unicity on 'myAttribute3' will be checked.
     *
     * If any of the constraints is not respected, a \SimpleAR\Exception is thrown.
     *
     * @var array
     */
    protected static $_uniqueConstraints = array();

    /**
     * This array defines order of rows when fetching multiple rows of this
     * model. Typical example: search function.
     *
     * @var array
     */
    protected static $_orderBy = array();

    /**
     * This array allows the creation of link (or relations) with
     * other models.
     *
     * It is constructed this way:
     * ```php
     *  array(
     *      'relation_name' => array(
     *          'type'              => <relation type>,
     *          'model'             => <model name>,
     *          'on_delete_cascade' => <bool>,
     *          <And other fields depending on relation type>
     *      ),
     *  ...
     *  );
     * ```
     *
     * In this section, "CM" stands for Current Model; "LM" stands for Linked
     * Model.
     *
     * Explanation of different fields:
     *
     *  * type: The relation type with the model. Can be "belongs_to", "has_one",
     *  "has_many" or "many_many" for the moment;
     *  * model: The model class name without the "Model" suffix. For example:
     *  "model" => "Company" for CompanyModel;
     *  * on_delete_cascade: If true, delete linked model(s).
     *
     * But there are also fields that depend on relation type. Let's see them:
     *
     *  * belongs_to:
     *
     *      * key_from: CM attribute that links to LM. Mandatory.
     *
     *  * has_one:
     *
     *      * key_from: CM field that links to LM.
     *        Optional. Default: CM::_mPrimaryKey.
     *        It is possible to change the value to a CM::_aColumns key if
     *        the link is not made on CM primary key.
     *
     *      * key_to:   LM field that links to CM.
     *        Optional. Default: UserModel => userId
     *        It is possible to change the value to a LM::_aColumns key if
     *        the link is not made in CM primary key or if the CM PK name is
     *        not the same that the LM foreign key name.
     *
     *  * has_many:
     *
     *      * key_from: CM unique field that links to key_to.
     *        Optional. Default: UserModel => userId
     *        Can be change if the link is not made on CM primary key.
     *
     *      * key_to:   LM field that links to key_from.
     *        Optional. Default: CM::_mPrimaryKey (same name).
     *
     *  * many_many:
     *
     *  If you want to use a middle model to join CM et LM, here are the fields
     *  you need:
     *
     *      * join_model: The join model class.
     *
     *      * join_from: Join model attribute that links to CM primary key.
     *        Optional. Default: ???
     *
     *      * join_to:   Join model attribute that links to LM primary key.
     *        Optional.
     *
     *  If you don't want to use a middle model to join CM et LM, here are the fields
     *  you need:
     *
     *      * join_table: The table that makes the join between the two models.
     *
     *      * join_from: Join table field that links to CM primary key.
     *        Optional. Default: CM::_mPrimaryKey.
     *
     *      * join_to:   Join table field that links to LM primary key.
     *        Optional. Default: LM::_mPrimaryKey.
     *
     *  In both case, you can optionnaly specify these fields:
     *
     *      * key_from: CM unique field that links to join_from. Default:
     *      CM::_mPrimaryKey.
     *
     *      * key_to : LM unique field that links to join_to. Default:
     *      LM::_mPrimaryKey.
     *
     *
     *  Example:
     *  ```php
     *  class Company extends SimpleAR\Model
     *  {
     *      protected $_relations = array(
     *          'products' => array(
     *              'type'     => 'has_many',   // Mandatory.
     *              'model'    => 'Product',    // Mandatory.
     *              'key_from' => 'product_id', // Optional.
     *          ),
     *      );
     *  }
     *  ```
     *
     * On top of these configuration fields, you can add the following fields:
     *
     *  * "conditions": An array of conditions that will be used to filter
     *  retrieved linked models.
     *  * "order_by": An order by array that will be used retrieving linked
     *  models.
     *
     *  Example:
     *  ```php
     *  class Company extends SimpleAR\Model
     *  {
     *      protected $_relations = array(
     *          'products' => array(
     *              'type'     => 'has_many',   // Mandatory.
     *              'model'    => 'Product',    // Mandatory.
     *              'key_from' => 'product_id', // Optional.
     *
     *              'conditions' => array(array('price', '>', 10.00), 'category' => array(12, 4, 7)),
     *              'order_by'   => array('price' => 'ASC'),
     *          ),
     *      );
     *  }
     *  ```
     *
     * @var array
     */
    protected static $_relations = array();

    /**
     * The model instance's ID.
     *
     * This is the primary key of the model. It contains the value of DB field
     * name corresponding to $_primaryKey.
     *
     * @var mixed
     */
    protected $_id;

    /**
     * The model instance's attribute list.
     *
     * It contains all *used* model instance attributes of the model. This array
     * is constructed in serveral places in the code. Let's review them!
     *
     * * When a row is fetched from database, an Model instance is created and its
     * $_attributes array is filled with all selected fields and their values;
     * * When you access to a relation: Linked models are fetched from database
     * and stored in this array;
     * * When you set an attribute that is not in this array yet;
     * * When access to a *count* attribute.
     *
     * @var array
     */
    protected $_attributes = array();

    /**
     * This attribute is used to decide if model instance really has to be
     * saved when using `save()` method. For instance, if no attribute has been
     * modified, there is no need to execute an UPDATE SQL query.
     *
     * @var bool
     */
    protected $_isDirty = false;

    /**
     * This attribute contains the current used filter.
     *
     * @var string
     */
    protected $_currentFilter;

    /**
     * This array contains the list of declared Table objects.
     *
     * Table objects contains data about the model table i.e. columns, table
     * name, primary key...
     *
     * We could have used static variables in Model instead (and actually, that
     * is what was done before), but it created problems with inheritance (even
     * with static keyword) when a static variable was not declared in subclass
     * and stuff... But I won't get to deep in there now.
     *
     * I know, many people would say that static is evil and blablabla, but I
     * don't agree and I think it is PHP implementation of it that is bad. (See
     * this
     * http://stackoverflow.com/questions/1203810/is-there-a-way-to-have-php-subclasses-inherit-properties-both-static-and-instan
     * and that https://bugs.php.net/bug.php?id=49105)
     *
     * There is one Table for each Model and this Table is instanciated and
     * stored into this array from within `Model::wakeup()` function.
     *
     * @var array
     * @see SimpleAR\Model::wakeup()
     */
    private static $_tables = array();

    /**
     * Constructor.
     *
     * The constructor methods is used to create a new Model instance from
     * scratch. Attribute array and some extra option can be passed it in.
     *
     * Besides, constructor will set default values if any specified in
     * `Model::$_defaultValues`.
     *
     * @param array $attributes Array of attributes to fill the new instance
     * with. The `Model::modify()` method will be used for this.
     *
     * @param array $options. An array option to extra configure the new
     * instance. It can contain following entries:
     *
     *  * "filter": An optional filter to apply to the model.
     *
     * @see SimpleAR\Model::modify()
     * @see SimpleAR\Model::_setDefaultValues()
     */
    public function __construct(array $attributes = null, array $options = null)
    {
        if (isset($options['filter']))
        {
            $this->setFilter($options['filter']);
        }

        $this->_setDefaultValues();

		if ($attributes)
		{
			$this->modify($attributes);
		}
    }

    /**
     * Property/Attribute getter.
     *
     * Since no public property is declared in `Model`, this method will be
     * fired every time user tries to get a *virtual property*.
     *
     * This function will check attribute existence in several places. Here are
     * the differents check, by order:
     *
     * 1. Is try-to-be-get attribute called "id"? If yes, return instance's ID;
     * 3. Is there a method called `get_<attribute name>()`? If yes, fire it and
     * return its result;
     * 2. Is in attribute array (`Model::$_attributes`). If there, return it;
     * 4. Is it a relation (in `Model::$_relations`)? If yes, load linked
     * 5. Is it a *count get*? If yes, compute it, store the result in attribute
     * array and return it.
     *
     * If, at this step, there is nothing found, throw an error (notice).
     *
     * Count get
     * ---------
     * What is a *count get*? It is a weird (?) syntax you can use in order to
     * retrieve totals i.e. number of products of an company.
     *
     * Example:
     * Let us say you have a Company model and a Product model. There is a
     * has_many relation from Company to Product named "products".
     * Then, you can do:
     *  ```php
     *  echo $myCompany->{'#products'};
     *  ```
     * to print the number of products the company has.
     *
     * There are three ways of retrieving a count. Here by order of priority:
     *
     * 1. There is a method called `count_<attribute name>()`. Fire it, store
     * the result in attributes array and return it;
     * 2. The count is already in attributes array. Return it;
     * 3. There is a corresponding relation is the model. Execute a COUNT query,
     * store the result in attribute array, return it.
     *
     * @param string $s The property name.
     * @return mixed
     */
    public function __get($s)
    {
        // Specific case for id.
        if ($s === 'id')
        {
            return $this->_id;
        }

        if (method_exists($this, 'get_' . $s))
        {
            return call_user_func(array($this, 'get_' . $s));
        }

        // Classic attribute.
        if (isset($this->_attributes[$s]) || array_key_exists($s, $this->_attributes))
        {
            return $this->_attributes[$s];
        }

        // Relation.
        // Will arrive here maximum once per relation because when a relation is
        // loaded, an attribute named as the relation is appended to $this->_attributes.
        // So, it would return it right above.
        if (isset(static::$_relations[$s]))
        {
            $this->_loadLinkedModel($s);

            if (method_exists($this, 'get_' . $s))
            {
                return call_user_func(array($this, 'get_' . $s));
            }

            return $this->_attributes[$s];
        }

        // Count. Rare case, that is why it is at the end.
		if ($s[0] === '#')
		{
			$baseName = substr($s, 1);

            if (method_exists($this, 'count_' . $baseName))
            {
                return $this->_attributes[$s] = call_user_func(array($this, 'count_' . $baseName));
            }

            if (isset($this->_attributes[$baseName]))
            {
                return $this->_attributes[$s] = count($this->_attributes[$baseName]);
            }

            if (isset(static::$_relations[$baseName]))
            {
                return $this->_attributes[$s] = $this->_countLinkedModel($baseName);
			}
		}


        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $s .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
		);

        return null;
    }

    /**
     * Test if an attribute is set.
     *
     * @param string $name The name of the attribute to test.
     * @return bool True if the attribute is set, false otherwise.
     */
    public function __isset($name)
    {
        return isset($this->_attributes[$name])
            || isset(static::$_relations[$name])
            || array_key_exists($name, $this->_attributes)
            || array_key_exists($name, static::$_relations)
            ;
    }

    /**
     * Property setter.
     *
     * Since no public property is declared in `Model`, this method will be
     * fired every time user tries to set a *virtual property*.
     *
     * This function will check existence of the property. You can set a
     * property any property you want.
     *
     * In all cases, the property you set will be store in the attribute array.
     * Nothing else is modified.
     *
     * However, if a method called `set_<property name>()` exists, it will be
     * fired instead of directly modify the attribute array.
     *
     * @param string $name  The property name.
     * @param mixed  $value The property value.
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if (method_exists($this, 'set_' . $name))
        {
            call_user_func(array($this, 'set_' . $name), $value);
        }
        else
        {
            $this->_attr($name, $value);
        }

        return;

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
		);
    }

    /**
     * Unset an attribute.
     *
     * @param string $name The name of the attribute to unset.
     */
    public function __unset($name)
    {
        unset($this->_attributes[$name]);
    }

    /**
     * This function allow to only *link* one model from a relation
     * without *unlink* all of the linked models (that would have been the case
     * when directly assigning the relation content via `=`.
     *
     * ```php
     * $user->addTo('applications', 12);
     * ```
     *
     * or
     *
     * ```php
     * $user->addTo('applications', $jobOffer);
     * ```
     *
     * @param string    $relation    The relation name.
     * @param int|Model $linkedModel What to add.
     *
     * @return void.
     */
    public function addTo($relation, $what)
    {
        $relation = static::relation($relation);

        if (! $relation instanceof Relation\ManyMany)
        {
            throw new Exception('addTo can only be used on ManyMany relations.');
        }

        if ($what instanceof Model)
        {
            $what->save();
            $id = $what->_id;
        }
        else
        {
            if ($what === null) { return; }

            $id = $what;
        }

        $table  = $relation->jm->table;
        $fields = array($relation->jm->from, $relation->jm->to);
        self::query()->insertInto($table, $fields)
            ->values(array($this->_id, $id))
            ->run();
    }

    public static function getGlobalConditions()
    {
        return static::table()->conditions;
    }

    public static function setGlobalConditions(array $conditions)
    {
        static::table()->conditions = $conditions;
    }

    /**
     * Return several Model instances according to an option array.
     *
     * This function is an alias for:
     *  ```php
     * Model::find('all', $options);
     *  ```
     * @param array $options An option array.
     * @return array
     *
     * @see SimpleAR\Model::find()
     returnublic static function all(array $options = array())
    {
        return self::find('all', $options);
    }

    /**
     * Transform a bunch of models into associative arrays.
     *
     * @param array $array An array of models to convert into array or an array
     * of attributes to loop through to recursively transform linked models to
     * arrays.
     *
     * @see Model::toArray()
     * @see Model::attributes()
     *
     * return array
     */
    public static function arrayToArray(array $array)
    {
        foreach ($array as &$m)
        {
            if (is_object($m))
            {
                if ($m instanceof Model)
                {
                    $m = $m->toArray();
                }
                elseif ($m instanceof DateTime)
                {
                    $m = $m->__toString();
                }
            }
            elseif (is_array($m))
            {
                $m = self::arrayToArray($m);
            }
        }

        return $array;
    }

    /**
     * Return attribute array plus the object ID.
     *
     * @return array The instance attributes and its ID.
     */
    public function attributes()
    {
        $attrs = array_diff_key(
            array('id' => $this->_id) + $this->_attributes,
            array_flip(static::$_excludedKeys)
        );

        foreach (static::$_includedKeys as $key)
        {
            $attrs[$key] = $this->$key;
        }

        return $attrs;
    }

    /**
     * Return the model columns.
     *
     * TO CHANGE: The keys are the attribute names, the values are the column names. Of
     * course, they can be identical. It depends on what have be defined in
     * static::$_columns;
     *
     * @return array
     * @see SimpleAR\Table
     */
    public static function columns()
    {
        return array_keys(static::table()->columns);
    }

    /**
     * Very useful function to get an array of the attributes to select from
     * database. Attributes are values of $_columns.
     *
     * @param string $filter Optional filter that prevents us to fetch all
     * attribute from the table.
     *
     * @param string $tableAlias The alias to use to designate the table.
     * @param string $resAlias   The alias to use to tidy resulting row up.
     *
     * @note: it will *not* throw any exception if filter does not exist; instead,
     * it will not use any filter.
     *
     * @return array
     */
    public static function columnsToSelect($filter = null)
    {
        $table = static::table();

        $res = ($filter !== null && isset(static::$_filters[$filter]))
            ? array_intersect_key($table->columns, array_flip(static::$_filters[$filter]))
            : $table->columns
            ;

        // Include primary key to columns to fetch. Useful only for simple
        // primary keys.
        if ($table->isSimplePrimaryKey)
        {
            $res['id'] = $table->primaryKey;
        }

        return $res;
    }

    /**
     * Return count of Model instances according to an option array.
     *
     * This function is an alias for:
     *  ```php
     * Model::find('count', $options);
     *  ```
     * @param array $options An option array.
     * @return array
     *
     * @see SimpleAR\Model::find()
     */
    public static function count(array $options = array())
    {
        return self::find('count', $options);
    }

    /**
     * This function creates a new Model instance and inserts it into database.
     *
     * This is just a shorthand for:
     *  ```php
     *  $o = new static($attributes);
     *  return $o->save();
     *  ```
     *
     * @param array $attributes Array of attributes to fill the new instance
     * with.
     *
     * @return $this
     *
     * @see SimpleAR\Model::__construct()
     * @see SimpleAR\Model::save()
     */
    public static function create(array $attributes = null)
    {
        $o = new static($attributes);
        return $o->save();
    }


    /**
     * Deletes current instance or linked models specified by a relation name.
     *
     * _onBeforeDelete() and _onAfterDelete() event functions will be fired.
     *
     * @param string $relation A relation name. If given, `delete()` will delete
     * corresponding linked models instead of delete the current object.
     *
     * @see Model::_deleteLinkedModel()
     * @see Model::_onBeforeDelete()
     * @see Model::_onAfterDelete()
     *
     * @return bool True on success.
     *
     * @throws Exception if object is not present in database.
     * @throws Exception if object ID is null.
     */
    public function delete($relation = null)
    {
        // Want to delete linked models? All right!
        if ($relation !== null && is_string($relation))
        {
            $this->_deleteLinkedModel($relation);
            return $this;
        }

        // Cannot delete a new model.
        if ($this->_id === null)
        {
            throw new Exception('Impossible to delete a new model instance.');
        }

        // Any last words to say?
        $this->_onBeforeDelete();

        $query = self::query()->delete()->where('id', $this->_id);
        $count = $query->rowCount();
        //$count = self::query('delete')->conditions(array('id' => $this->_id))->rowCount();

        // Was not here? Weird. Tell user.
        if ($count === 0)
        {
            throw new RecordNotFound($this->_id);
        }

        // If the database is lazy, save integrity for it.
        if (Cfg::get('doForeignKeyWork'))
        {
            $this->_deleteLinkedModels();
        }

        $this->_onAfterDelete();
        $this->_id = null;

        return $this;
    }

    /**
     * Adds one or several fields in excluding filter array.
     *
     * @param string|array $keys The key(s) to add in the array.
     */
    public static function exclude($key)
    {
        foreach ((array) $key as $key)
        {
            static::$_excludedKeys[] = $key;
        }
    }

    public static function keep($key)
    {
        foreach ((array) $key as $key)
        {
            static::$_includedKeys[] = $key;
        }
    }

    /**
     * Check if a model instance represented by its ID exists.
     *
     * @param mixed $m             Can be either the ID to test on or a condition array.
     * @param bool  $byPrimaryKey  Should $m be considered as a primary key or as a condition
     * array?
     *
     * @return bool True if it exists, false otherwise.
     */
    public static function exists($m, $byPrimaryKey = true)
    {
        // Classic exists(): By primary key.
        if ($byPrimaryKey)
        {
            try
            {
                static::findByPK($m);
                return true;
            }
            catch (RecordNotFound $ex)
            {
                return false;
            }
        }

        // We want to test record existence by an array of condtitions.
        return (bool) static::find('first', array('conditions' => $m));

    }


    /**
     * Sets the filter array. When we output the instance,
     * only fields contained in the filter array will be output.
     *
     * @param array|string $filter The filter array that contains all fields we want to
     * keep when output OR the name of the filter we want to apply.
     *
     * @return $this
     */
    public function setFilter($filter)
    {
        // It is an attribute array.
        if (is_array($filter))
        {
            $this->_whitelist = $filter;
        }

        // It is a filter name.
        else
        {
            // Wrong name, tell user.
            if (! isset(static::$_filters[$filter]))
            {
                throw new Exception('Filter "' . $filter . '" does not exist for model "' . get_class() . '".');
            }

            $this->_whitelist     = static::$_filters[$filter];
            $this->_currentFilter = $filter;
        }

        return $this;
    }

    /**
     * General finder method.
     *
     * This methods allows you to retrieve models from database. You can process several differents
     * finds. First parameter specifies find type:
     *
     *  * "all": Retrieve several Model instances. Function will return an array;
     *  * "first": Retrieve first found Model. Function will return a Model instance;
     *  * "last": Retrieve last found Model. Function will return a Model instance;
     *  * "count": Return number of found Models.
     *
     * @param mixed $first Can be a ID to search on (shorthand for `Model::findByPK()`) or the find
     * type. For this to be considered as an ID, you must pass an integer or an array.
     * @param array $options An option array to find models.
     *
     * @return mixed
     *
     * @throws Exception When first parameter is invalid.
     */
    public static function find($first, array $options = array())
    {
        // Add global conditions.
        if ($conditions = self::getGlobalConditions()) {
            if (isset($options['conditions'])) {
                $options['conditions'] = array_merge($options['conditions'], $conditions);
            } else {
                $options['conditions'] = $conditions;
            }
        }

        // Find by primary key. It can be an array when using compound primary
        // keys.
        if (is_int($first) || is_array($first))
        {
            return self::findByPK($first, $options);
        }

        // $first is the type of result we want. It can be:
        // "first", "last", "one", "all" or "count".
        $get   = $first;
        $query = self::query()->setOptions($options);

        return $query->$get();
    }

    /**
     * Retrieve object by primary key.
     *
     * @param mixed $id      The object ID or an array of object IDs.
     * @param array $options Option array.
     *
     * @return mixed.
     */
    public static function findByPK($id, array $options = array())
    {
        // Handles multiple primary keys, too.
        $options['conditions']['id'] = $id;

        $get = 'one';
        // If $id is an array, the user may want several objects.
        if (is_array($id))
        {
            // But only if the primary key is not compound *or*, if it is
            // compound, if $id is multidimensional.
            if (self::table()->isSimplePrimaryKey || is_array($id[0]))
            {
                $get = 'all';
            }

            if (! self::table()->isSimplePrimaryKey && (! isset($id[0]) || ! is_array($id[0]))) {
                $id = array($id);
            }

            if (! self::table()->isSimplePrimaryKey && (! isset($id[0]) || ! is_array($id[0]))) {
                $id = array($id);
            }
        }

        $query = self::query()->setOptions($options);
        if (! $res = $query->$get())
        {
            throw new RecordNotFound($id);
        }

        return $res;
    }

    /**
     * Retrieve first found object of database.
     *
     * @param array $options The option array.
     * @return mixed.
     *
     * @see Model::find()
     */
    public static function first(array $options = array())
    {
        return self::find('first', $options);
    }

    /**
     * Alias for first().
     *
     * @see Model::one()
     */
    public static function one(array $options = array())
    {
        return self::first($options);
    }

    /**
     * Retrieve last found object of database.
     *
     * @param array $options The option array.
     * @return mixed.
     *
     * @see Model::find()
     */
    public static function last(array $options = array())
    {
        return self::find('last', $options);
    }

    /**
     * This function checks that current object is linked to another.
     *
     * It is the matching of addTo() and removeFrom() methods but it works with
     * any type of relation.
     *
     * @param string $relation The name of the relation to check on.
     * @param mixed  $what     Linked object or object's ID.
     *
     * @return bool True if current object is linked to the other, false
     * otherwise.
     *
     * @see Model::addTo()
     * @see Model::removeFrom()
     */
    public function has($relation, $what = null)
    {
        $lms = $this->__get($relation);

        // User just wants to know if current object has any linked model
        // through this relation.
        //
        // @TODO We should process an EXISTS query instead of retrieving linked
        // models.
        if ($what === null)
        {
            return (bool) $lms;
        }

        // User wants to know if current object is linked to $what.

        // We want to test by IDs, not by objects.
        $id = $what instanceof Model
            ? $what->id
            : $what
            ;

        // $lms is the array of Linked ModelS.
        // $lm  is a Linked Model instance.
        if (! is_array($lms)) { $lms = array($lms); }
        foreach ($lms as $lm)
        {
            // I would like to use === operator but we are not sure that int IDs
            // really are integers.
            if ($lm->id == $id)
            {
                return true;
            }
        }

        return false;
    }

    public function isDirty()
    {
        return $this->_isDirty;
    }

    /**
     * Manually load linked model(s). Useful to refresh relationships.
     *
     * @param string $relation The relation to load.
     *
     * @return void
     */
    public function load($relation, array $options = array())
    {
        $this->_loadLinkedModel($relation, $options);

        return $this->$relation;
    }

    /**
     * Modify attributes of current object.
     *
     * @param array $attributes Associative array containing attributes to
     * modify and the new values to set.
     *
     * @return $this
     */
    public function modify(array $attributes)
    {
        foreach ($attributes as $key => $value)
        {
            $this->__set($key, $value);
        }

        return $this;
    }

    /**
     * Alias of modify().
     *
     * @see SimpleAR\Model::modify()
     */
    public function set(array $attributes)
    {
        return $this->modify($attributes);
    }

    /**
     * Get or set the relation identified by its name.
     *
     * If second parameter is null, it works like a getter.
     * It works like a setter if second parameter is not null.
     *
     * @param $name     The name of the relation.
     * @param $relation The relation to set instead
     *
     * @return object A Relation object.
     * @throw SimpleAR\Exception if used as getter and $relationName is unknown.
     */
    public static function relation($name, Relation $relation = null)
    {
		if ($relation === null)
		{
			if (! isset(static::$_relations[$name]))
			{
				throw new Exception('Relation "' . $name . '" does not exist for class "' . get_called_class() . '".');
			}

            // Avoid array accesses.
            $val =& static::$_relations[$name];

			// If relation is not yet initlialized, do it.
			if (! $val instanceof Relation)
			{
				$val = Relation::forge($name, $val, get_called_class());
			}

			// Return the Relation object.
			return $val;
		}
		else
		{
			static::$_relations[$name] = $relation;
		}
    }

    /**
     * Delete rows from database.
     *
     * @param array $conditions A condition option array.
     * @return int Number of affected rows.
     */
    public static function remove(array $conditions = null)
    {
        return self::query()
                ->delete()
                ->conditions($conditions)
                ->rowCount();
    }

    /**
     * This function allow to only *unlink* one model from a relation
     * without unlink all of the linked models (that would have been the case
     * when directly assigning the relation content via `=`.
     *
     * ```php
     * $user->removeFrom('applications', 12);
     * ```
     *
     * or
     *
     * ```php
     * $user->removeFrom('applications', $jobOffer);
     * ```
     *
     * @param string    $relation    The relation name.
     * @param int|Model $linkedModel What to remove.
     *
     * @return void.
     */
    public function removeFrom($relation, $what)
    {
        $relation = static::relation($relation);

        if (! $relation instanceof Relation\ManyMany)
        {
            throw new Exception('removeFrom can only be used on ManyMany relations.');
        }

        $id = $what instanceof Model
            ? $what->_id
            : $what;

        if ($id === null) { return; }

        self::query()->deleteFrom($relation->jm->table)
            ->conditions(array(
                $relation->jm->from => $this->_id,
                $relation->jm->to   => $what,
            ))
            ->run();
    }

    /**
     * Saves the instance to the database. Will insert it if not yet created or
     * update otherwise.
     *
     * @return bool True if saving is OK, false otherwise.
     */
    public function save()
    {
        if ($this->_isDirty)
        {
            if ($this->_id === null)
            {
                $this->_insert();
            }
            else
            {
                $this->_update();
            }

            $this->_isDirty = false;
        }

        return $this;
    }

	/**
     * Search for object in database. It combines count() and all() functions.
     *
	 * This function makes pagination easier.
	 *
     * @param array $options The option array.
	 * @param int   $page    Page number. Min: 1.
	 * @param int   $nbItems Number of items. Min: 1.
	 */
	public static function search(array $options, $page, $nbItems)
	{
		$page    = $page    >= 1 ? $page    : 1;
		$nbItems = $nbItems >= 1 ? $nbItems : 1;

		$options['limit']  = $nbItems;
		$options['offset'] = ($page - 1) * $nbItems;

		$res['count'] = static::count($options);
		$res['rows']  = static::all($options);

		return $res;
	}

    /**
     * Return the Table object corresponding to the current model class.
     *
     * @return SimpleAR\Table
     *
     * @see SimpleAR\Model::$_tables
     */
    public static function table()
    {
        $a = explode('\\', get_called_class());

        return self::$_tables[array_pop($a)];
    }

    /**
     * Transforms current object into an array.
     *
     * Array will contains all currently defined attributes.
     *
     * @return array
     * @see SimpleAR\Model::attributes()
     */
    public function toArray()
    {
        return self::arrayToArray($this->attributes());
    }

    public static function createFromRow($row, array $options = array())
    {
        $instance = new static(null, $options);
        $instance->_load($row);

        return $instance;
    }

    /**
     * Initialize current model class.
     *
     * This function is called by the SimpleAR autoloading system.
     *
     * What does it do?
     * ----------------
     *
     *  1. Initiliaze non defined information by convention (table name, primary
     *  key, columns...)
     *
     *  2. Create a unique Table instance for this model class.
     *
     *  @return void
     */
    public static function wakeup()
    {
		// get_called_class() returns the namespaced class. So we have to parse
		// it.
		$a = explode('\\', get_called_class());
		$currentClass = array_pop($a);

		// Set defaults for model classes
		if ($currentClass != 'Model')
		{
            $suffix        = Cfg::get('modelClassSuffix');
            $modelBaseName = $suffix ? strstr($currentClass, $suffix, true) : $currentClass;

            $tableName  = static::$_tableName  ?: call_user_func(Cfg::get('classToTable'), $modelBaseName);
            $primaryKey = static::$_primaryKey ?: Cfg::get('primaryKey');

            // Columns are defined in model, perfect!
			if (static::$_columns)
			{
                $columns = static::$_columns;
			}
			// They are not, fetch them from database.
            else
            {
                $columns = self::query()->getTableColumns($tableName);

                // We do not want to insert primary key in
                // static::$_columns unless it is a compound key.
                if (is_string($primaryKey))
                {
                    unset($columns[$primaryKey]);
                }
            }

            $table = new Table($tableName, $primaryKey, $columns);
            $table->order       = static::$_orderBy;
            $table->modelBaseName = $modelBaseName;

            self::$_tables[$currentClass] = $table;
		}
    }

    /**
     * Create and return a new QueryBuilder instance.
     *
     * @return QueryBuilder
     */
    public static function query()
    {
        return new QueryBuilder(get_called_class());
    }

    /**
     * Handle dynamic static calls.
     *
     * This function transmits methods calls to a new Query.
     * This allows code like:
     *
     *  ```php
     *  Blog::with('articles')->one();
     *
     *  // or
     *
     *  Article::limit(12)->all();
     *  ```
     *
     *  instead of:
     *
     *  ```php
     *  Blog::one(array('with' => 'articles'));
     *
     *  // or
     *
     *  Article::all(array('limit' => 12));
     *  ```
     */
    public static function __callStatic($method, $args)
    {
        $query = self::query();

        return call_user_func_array(array($query, $method), $args);
    }

    /**
     * Direct Getter/Setter.
     *
     * Directly get or set a value of an attribute without using any magic.
     *
     * @param string $name The attribute name.
     *
     * There is no second optional argument in order to allow function caller to
     * set a NULL value because in that case we could not know if user wants to
     * get a value or set it. So, the function uses func_num_args() to know what
     * action to process.
     *
     * @return void|mixed
     */
    protected function _attr($name)
    {
        if (func_num_args() === 1)
        {
            return $this->_attributes[$name];
        }
        else
        {
            $value = func_get_arg(1);
            if (! isset($this->_attributes[$name])
                || $value !== $this->_attributes[$name])
            {
                $this->_attributes[$name] = $value;
                $this->_isDirty = true;
            }
        }
    }

    /**
     * The goal of this function is to check that the resource is not already
     * stored in database. Indeed, some user may insert offer without using the
     * primary key. So submodels implemented for such users should check the
     * presence of the resource.
     *
     * @return $this
     * @throws Exception if a constraint is not verified.
     */
    protected function _checkUniqueConstraints()
    {
        foreach (static::$_uniqueConstraints as $constraint)
        {
            // Construct conditions array we will use to check if a row exists.
            $conditions = array();
            foreach ($constraint as $attribute)
            {
                $conditions[$attribute] = $this->__get($attribute);
            }

            // Row already exists, we cannot insert a new row with these values.
            if (self::exists($conditions))
            {
                throw new Exception('Violate unique constraint: (' . implode(', ', $constraint) . ').');
            }
        }

        return $this;
    }

    /**
     * Event function called after object is deleted.
     *
     * @see SimpleAR\Model::delete();
     */
    protected function _onAfterDelete()
    {
    }

    /**
     * Event function called after object is inserted.
     *
     * @see SimpleAR\Model::_insert();
     */
    protected function _onAfterInsert()
    {
    }

    /**
     * Event function called after object is loaded.
     *
     * @see SimpleAR\Model::_load();
     */
    protected function _onAfterLoad()
    {
    }

    /**
     * Event function called after object is updated.
     *
     * @see SimpleAR\Model::_update();
     */
    protected function _onAfterUpdate()
    {
    }

    /**
     * Event function called before object is deleted.
     *
     * @see SimpleAR\Model::delete();
     */
    protected function _onBeforeDelete()
    {
    }

    /**
     * Event function called before object is inserted.
     *
     * @see SimpleAR\Model::_insert();
     */
    protected function _onBeforeInsert()
    {
    }

    /**
     * Event function called before object is loaded.
     *
     * @see SimpleAR\Model::_load();
     */
    protected function _onBeforeLoad()
    {
    }

    /**
     * Event function called before object is updated.
     *
     * @see SimpleAR\Model::_update();
     */
    protected function _onBeforeUpdate()
    {
    }

    /**
     * Retrieve number of linked models linked by a relation.
     *
     * Of course, this function is useful only with HasMany and ManyMany
     * relationships.
     *
     * Count value will be stored in the attribute array with the following
     * name: `#$relationName`.
     *
     * You can access it this way:
     *
     *  ```php
     *  echo $myObject->{'#relationName'};
     *  ```
     *
     * @param string $relationName The relation name.
     */
    private function _countLinkedModel($relationName)
    {
        $relation = static::relation($relationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        if ($this->_id === null)
        {
			return 0;
        }

        // Our object is already saved. It has an ID. We are going to
        // fetch potential linked objects from DB.
		$res     = 0;
		$class = $relation->lm->class;

        if ($relation instanceof Relation\BelongsTo)
        {
            return $this->__get($relation->cm->attribute) === null ? 0 : 1;
        }
        elseif ($relation instanceof Relation\HasOne || $relation instanceof Relation\HasMany)
        {
            $res = $class::count(array(
                'conditions' => array_merge($relation->conditions, array($relation->lm->attribute => $this->__get($relation->cm->attribute))),
            ));
        }
        else // ManyMany
        {
			$reversed = $relation->reverse();

			$class::relation($reversed->name, $reversed);

			$conditions = array_merge(
				$reversed->conditions,
				array($reversed->name . '/' . $reversed->lm->attribute => $this->__get($reversed->cm->attribute))
			);

			$res = $class::count(array(
                'conditions' => $conditions,
			));
        }

        return $res;
    }

    /**
     * Delete linked models of a relationship.
     *
     * Proper user method is delete()
     *
     * @param string $relationName The name of the relation.
     *
     * @return void
     * @see SimpleAR\Model::delete()
     */
    private function _deleteLinkedModel($relationName)
    {
        $relation = static::relation($relationName);

        if ($relation instanceof Relation\ManyMany)
        {
            $relation->deleteJoinModel($this->_id);
        }

        $relation->deleteLinkedModel($this->_id);
    }

    /**
     * This function deletes linked models.
     *
     * More precisely it will delete linked models only if the field
     * "on_delete_cascade" of the relation array config is set at true. However,
     * for has_many relations, if there is a join table defined in the relation
     * array, all rows of the table linking the current model ($this) will be
     * removed.
     *
     * @link ModelAbstract::_relations for more information on relations
     * between models.
     */
    private function _deleteLinkedModels()
    {
        foreach (static::$_relations as $name => $m)
        {
            $relation = static::relation($name);

            if ($relation instanceof Relation\ManyMany)
            {
                $relation->deleteJoinModel($this->_id);
            }

			if ($relation->onDeleteCascade)
			{
				$relation->deleteLinkedModel($this->_id);

				if ($relation instanceof Relation\HasOne)
				{
					$this->_attributes[$name] = null;
				}
				else // HasMany || ManyMany
				{
					$this->_attributes[$name] = array();
				}
			}
        }
    }

    /**
     * Hydrate the object.
     *
     * Does not call any setter.
     * Does not set _isDirty flag to true.
     *
     * @param array $attributes Associative array containing the attributes to
     * set.
     *
     * @return void.
     */
    private function _hydrate(array $attributes)
    {
        foreach ($attributes as $key => $value)
        {
            $this->_attributes[$key] = $value;
        }
    }

    /**
     * Inserts the object in database.
     *
     * @return bool True on success, false otherwise.
     * @throws \PDOException if a database error occurs.
     *
     * @see SimpleAR\Model::_onBeforeInsert()
     * @see SimpleAR\Model::_onAfterInsert()
     */
    private function _insert()
    {
        $this->_checkUniqueConstraints();
        $this->_onBeforeInsert();

        $table   = static::table();
        $columns = $table->columns;

        // Keys will be attribute names; Values will be attributes values.
        $fields = array();

        // Will contains LM to save in cascade.
        $linkedModels = array();

        // Avoid retrieving config option several times.
        $dateTimeFormat = Cfg::get('databaseDateTimeFormat');

        foreach ($this->_attributes as $key => $value)
        {
            // Handle actual columns.
            if (isset($columns[$key]) || $key === 'id')
            {
                // Transform DateTime object into a database-formatted string.
                if ($value instanceof \DateTime)
                {
                    $value = $value->format($dateTimeFormat);
                }

                $fields[$key] = $value;
                continue;
            }

            // Handle linked models.
            // $value can be:
            // - a Model instance not saved yet (BelongsTo or HasOne relations);
            // - a Model instance ID (BelongsTo or HasOne relations) (Not
            // implemented).
            //
            // - a Model instance array (HasMany or ManyMany relations);
            // - a Model instance ID array (HasMany or ManyMany relations).
            if (isset(static::$_relations[$key]))
            {
                $relation = static::relation($key);

                // If it is linked by a BelongsTo instance, update local field.
                if ($relation instanceof Relation\BelongsTo)
                {
                    // Save in cascade.
                    $value->save();

                    // We use array_merge() and array_combine() in order to handle composed keys.
                    $fields = array_merge($fields, array_combine((array) $relation->cm->attribute, (array) $value->id));
                }
                // Otherwise, handle it later (After CM insert, actually, because we need CM ID).
                else
                {
                    $linkedModels[] = array('relation' => static::relation($key), 'object' => $value);
                }
            }
        }

        try
        {
            $lastId = self::query()->insert(array_keys($fields), array_values($fields));

            // We fetch the ID.
            if ($table->isSimplePrimaryKey) {
                $this->_id = $lastId;
            } else {
                $id = array();
                foreach ($table->primaryKey as $attribute) {
                    $id[] = $this->$attribute;
                }

                $this->_id = $id;
            }

            // Process linked models.
            // We want to save linked models on cascade.
            foreach ($linkedModels as $a)
            {
                // Avoid multiple array accesses.
                $relation = $a['relation'];
                $object   = $a['object'];

                // Ignore if not Model instance.
                if ($relation instanceof Relation\HasOne)
                {
                    if($object instanceof Model)
                    {
                        $object->{$relation->lm->attribute} = $this->_id;
                        $object->save();
                    }
                }
                elseif ($relation instanceof Relation\HasMany)
                {
                    // Ignore if not Model instance.
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $object as $o)
                    {
                        if ($o instanceof Model)
                        {
                            $o->{$relation->lm->attribute} = $this->_id;
                            $o->save();
                        }
                    }
                }
                else // ManyMany
                {
                    // We want to:
                    //  - save linked model instances if not done yet;
                    //  - consider integers as linked model instance IDs;
                    //  - link these instances with current model via the
                    //  relation join table;
                    //  - save instances on cascade.
                    $values = array();
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $object as $m)
                    {
                        if ($m instanceof Model)
                        {
                            $m->save();
                            $values[] = array($this->_id, $m->_id);
                        }
                        // Else we consider this is an ID.
                        else
                        {
                            $values[] = array($this->_id, $m);
                        }
                    }

                    // Run insert query only if there are values to insert. It
                    // would throw a PDO Exception otherwise.
                    if ($values)
                    {
                        $fields = array($relation->jm->from, $relation->jm->to);
                        self::query()->insertInto($relation->jm->table, $fields)
                            ->values($values)->run();
                    }
                }
            }
        }
        catch (DatabaseEx $ex)
        {
            throw $ex;
        }
        catch(Exception $ex)
        {
            throw new Exception('Error inserting ' . get_class($this) . ' in database.', 0, $ex);
        }

        // Other actions.
        $this->_onAfterInsert();

        return $this;
    }

    /**
     * Loads the model instance from the database.
     *
     * @param array $row It is the array directly given by the
     * \PDOStatement:fetch() function. Every field contained in this  parameter
     * correspond to attribute name, not to column's (@see columnsToSelect()).
     *
     * @return $this
     *
     * @see SimpleAR\Model::_onBeforeLoad()
     * @see SimpleAR\Model::_onAfterLoad()
     */
    private function _load(array $row)
    {
        // Need to prepare?
        $this->_onBeforeLoad();

        $table = static::table();

        // We set our object ID.
        if ($table->isSimplePrimaryKey)
        {
            $this->_id = $row['id'];
            unset($row['id']);
        }
        else
        {
            $this->_id = array();

            foreach ($table->primaryKey as $attribute)
            {
                $this->_id[] = $row[$attribute];
                //unset($row[$key]);
            }
        }

        // Eager load.
        if (isset($row['_WITH_']))
        {
            foreach ($row['_WITH_'] as $relation => $value)
            {
                $relation = static::relation($relation);
                $class  = $relation->lm->class;

                if ($relation instanceof Relation\BelongsTo || $relation instanceof Relation\HasOne)
                {
                    // $value is an array of attributes. The attributes of the linked model
                    // instance.
                    // But it *might* be an array of arrays of attributes. For instance, when
                    // relation is defined as a Has One relation but actually is a Has Many in
                    // database. In that case, SQL query would return several rows for this relation
                    // and we would result with $value to be an array of arrays.

                    // Array of arrays ==> array of attribute.
                    if (isset($value[0])) { $value = $value[0]; }

                    $o = new $class();
                    $o->_load($value);

                    if ($o->id !== null)
                    {
                        $this->_attr($relation->name, $o);
                    }
                }
                else
                {
                    // $value is an array of arrays. These subarrays contain attributes of linked
                    // models.
                    // But $value can directly be an associative array (if SQL query returned only
                    // one row). We have to check this, then.

                    $a = array();

                    if ($value)
                    {
                        // $value is an attribute array.
                        // Array of attributes ==> array of arrays.
                        if (! isset($value[0])) { $value = array($value); }

                        foreach ($value as $attributes)
                        {
                            $o = new $class();
                            $o->_load($attributes);

                            if ($o->id !== null)
                            {
                                $a[] = $o;
                            }
                        }
                    }

                    $this->_attr($relation->name, $a);
                }
            }

            unset($row['_WITH_']);
        }

        $this->_hydrate($row, false);

        if (Cfg::get('convertDateToObject'))
        {
            foreach ($this->_attributes as $key => &$value)
            {
                // We test that is a string because setters might have been
                // called from within _hydrate() so we cannot be sure of what
                // $value is.
                //
                // strpos call <=> $key.startsWith('date')
                if (is_string($value) && strpos($key, 'date') === 0)
                {
                    // Do not process "NULL-like" values (0000-00-00 or 0000-00-00 00:00). It would
                    // cause strange values.
                    // @see http://stackoverflow.com/questions/10450644/how-do-you-explain-the-result-for-a-new-datetime0000-00-00-000000
                    $value =  $value === '0000-00-00'
                            || $value === '0000-00-00 00:00:00'
                            || $value === null
                            ? null
                            : new DateTime($value)
                            ;
                }
            }
        }

        $this->_onAfterLoad();
        $this->_isDirty = false;

        return $this;
    }

    /**
     * Loads a model defined in model relations array.
     *
     * @param string $name The relations array entry.
     *
     * @return Model|array|null
     */
    private function _loadLinkedModel($relationName, array $localOptions = array())
    {
        $relation = static::relation($relationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        //
        // However, it is ok for BelongsTo since it does not rest on current
        // instance's ID.
        if ($this->_id === null && ! $relation instanceof Relation\BelongsTo)
        {
            if ($relation instanceof Relation\HasOne)
            {
                return null;
            }
            else // HasMany || ManyMany
            {
                return array();
            }
        }

        // Our object is already saved. It has an ID. We are going to
        // fetch potential linked objects from DB.
        $res	  = null;
		$class = $relation->lm->class;

        $options['order_by'] = $relation->order;
        $options['filter']   = $relation->filter;

        if ($relation instanceof Relation\BelongsTo || $relation instanceof Relation\HasOne)
        {
            $options['conditions'] = array_merge(
                $relation->conditions,
                array($relation->lm->attribute => $this->__get($relation->cm->attribute)),
                $class::getGlobalConditions()
            );

            $options = array_merge($options, $localOptions);

            $res = $class::first($options);
        }
        elseif ($relation instanceof Relation\HasMany)
        {
            $options['conditions'] = array_merge(
                $relation->conditions,
                array($relation->lm->attribute => $this->__get($relation->cm->attribute)),
                $class::getGlobalConditions()
            );

            $options = array_merge($options, $localOptions);

            $res = $class::all($options);
        }
        else // ManyMany
        {
			$reversed = $relation->reverse();

			$class::relation($reversed->name, $reversed);

			$options['conditions'] = array_merge(
				$reversed->conditions,
				array($reversed->name . '/' . $reversed->lm->attribute => $this->__get($reversed->cm->attribute)),
                $class::getGlobalConditions()
			);

            $options = array_merge($options, $localOptions);

			$res = $class::all($options);
        }

        $this->_attr($relationName, $res);
    }

    /**
     * Set default attribute values defined in $_defaultValues.
     *
     * @see Model::_hydrate()
     *
     * return void
     */
    private function _setDefaultValues()
    {
        $this->_hydrate(static::$_defaultValues);
    }

    /**
     * Updates a instance.
     *
     * @throws Exception if a database error occurs.
     * @return $this
     *
     * @see SimpleAR\Model::_onBeforeUpdate()
     * @see SimpleAR\Model::_onAfterUpdate()
     */
    private function _update()
    {
        $this->_onBeforeUpdate();

        $table   = static::table();
        $columns = $table->columns;

        // Keys will be attribute names; Values will be attributes values.
        $fields = array();

        // Will contains LM to save in cascade.
        $linkedModels = array();

        // Avoid retrieving config option several times.
        $dateTimeFormat = Cfg::get('databaseDateTimeFormat');

        foreach ($this->_attributes as $key => $value)
        {
            // Handles actual columns.
            if (isset($columns[$key]))
            {
                // Transform DateTime object into a database-formatted string.
                if ($value instanceof \DateTime)
                {
                    $value = $value->format($dateTimeFormat);
                }

                $fields[$key] = $value;
                continue;
            }

            // Handle linked models.
            // $value can be:
            // - a Model instance not saved yet (BelongsTo or HasOne relations);
            // - a Model instance ID (BelongsTo or HasOne relations) (Not
            // implemented).
            //
            // - a Model instance array (HasMany or ManyMany relations);
            // - a Model instance ID array (HasMany or ManyMany relations).
            if (isset(static::$_relations[$key]))
            {
                $relation = static::relation($key);

                // If it is linked by a BelongsTo instance, update local field.
                if ($relation instanceof Relation\BelongsTo)
                {
                    // Save in cascade.
                    $value->save();

                    // We use array_merge() and array_combine() in order to handle composed keys.
                    $fields = array_merge($fields, array_combine((array) $relation->cm->attribute, (array) $value->id));
                }
                // Otherwise, handle it later (After CM insert, actually).
                else
                {
                    $linkedModels[] = array('relation' => static::relation($key), 'object' => $value);
                }
            }
        }

        try
        {
            $query = self::query()->update()->set($fields)->where('id', $this->_id)->run();

            // I know code seems (is) redundant, but I am convinced that it is
            // better this way. Treatment of linked model can be different
            // during insert than during update. See ManyMany treatment for
            // example: we delete before inserting.
            foreach ($linkedModels as $a)
            {
                // Avoid multiple array accesses.
                $relation = $a['relation'];
                $object   = $a['object'];

                // Ignore if not Model instance.
                if ($relation instanceof Relation\HasOne)
                {
                    if($object instanceof Model)
                    {
                        $object->__set($relation->lm->attribute, $this->_id);
                        $object->save();
                    }
                }
                elseif ($relation instanceof Relation\HasMany)
                {
                    // Ignore if not Model instance.
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $object as $o)
                    {
                        if ($o instanceof Model)
                        {
                            $o->__set($relation->lm->attribute, $this->_id);
                            $o->save();
                        }
                    }
                }
                else // ManyMany
                {
                    // We want to:
                    //  - save linked model instances if not done yet;
                    //  - consider integers as linked model instance IDs;
                    //  - save instances on cascade.
                    //  - link these instances with current model via the
                    //  relation join table. To do this:
                    //      1) Delete all rows of join table that concerns
                    //      current model;
                    //      2) Insert new rows.

                    // Remove all rows from join table. (Easier this way.)
                    self::query()->delete()->where($relation->jm->from, $this->_id)->run();

                    $values = array();
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $object as $m)
                    {
                        if ($m instanceof Model)
                        {
                            $m->save();
                            $values[] = array($this->_id, $m->_id);
                        }
                        // Else we consider this is an ID.
                        else
                        {
                            $values[] = array($this->_id, $m);
                        }
                    }

                    // Run insert query only if there are values to insert. It
                    // would throw a PDO Exception otherwise.
                    if ($values)
                    {
                        $fields = array($relation->jm->from, $relation->jm->to);
                        $query = self::query()
                            ->insertInto($relation->jm->table, $fields)
                            ->values($values)
                            ->run();
                    }
                }
            }
        }
        catch (DatabaseEx $ex)
        {
            throw $ex;
        }
        catch (Exception $ex)
        {
            throw new Exception('Update failed for ' . get_class($this) . ' with ID: ' . $this->_id .'.', 0, $ex);
        }

        $this->_onAfterUpdate();

        return $this;
    }

}
