<?php
/**
 * This file contains the Model class.
 *
 * @author Damien Launay
 */
namespace SimpleAR;

/**
 * This class is the super class of all models.
 * It defines some basic functions shared by all models.
 *
 * @author Damien Launay
 */
abstract class Model
{
    /**
     * The database handler instance.
     *
     * @var \SimpleAR\Database
     */
    protected static $_db;

    /**
     * Contains configuration.
     *
     * @var \SimpleAR\Config
     */      
    protected static $_config;

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
     * with. The `Model::_hydrate()` method will be used for this.
     *
     * @param array $options. An array option to extra configure the new
     * instance. It can contain following entries:
     *
     *  * "filter": An optional filter to apply to the model.
     *
     * @see SimpleAR\Model::_hydrate()
     * @see SimpleAR\Model::_setDefaultValues()
     */
    public function __construct($attributes = array(), $options = array())
    {
        if (isset($options['filter']))
        {
            $this->filter($options['filter']);
        }

		if ($attributes)
		{
			$this->modify($attributes);
		}

        $this->_setDefaultValues();
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
     * 2. Is in attribute array (`Model::$_attributes`). If there, return it;
     * 3. Is there a method called `get_<attribute name>()`? If yes, fire it and
     * return its result;
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

        // Classic attribute.
        if (isset($this->_attributes[$s]) || array_key_exists($s, $this->_attributes))
        { 
            return $this->_attributes[$s];
        }

        if (method_exists($this, 'get_' . $s))
        {
            return call_user_func(array($this, 'get_' . $s));
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
    public function addTo($relation, $linkedModel)
    {
        $relation = static::relation($relation);

        if ($relation instanceof ManyMany)
        {
            if ($linkedModel instanceof Model)
            {
                $linkedModel->save();
                $id = $linkedModel->_id;
            }
            else
            {
                $id = $linkedModel;
            }

            $query = Query::insert(array(
                'fields' => array($relation->jm->from, $relation->jm->to),
                'values' => array($this->_id, $id),
            ), $relation->jm->table);

            $query->run();
        }
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
     */
    public static function all($options = array())
    {
        return self::find('all', $options);
    }

    public static function arrayToArray($array)
    {
        foreach ($array as &$m)
        {
            if (is_object($m) && $m instanceof Model)
            {
                $m = $m->toArray();
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
        return array('id' => $this->_id) + $this->_attributes;
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
    public static function count($options = array())
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
    public static function create($attributes)
    {
        $o = new static($attributes);
        return $o->save();
    }


    /**
     * Deletes the instance.
     *
     * Default behaviour (implemented here) uses primary key to delete the
     * object.
     *
     * @return bool True on success.
     * @throws Exception if object is not present in database.
     */
    public function delete($relationName = null)
    {
        if ($relationName !== null)
        {
            $this->_deleteLinkedModel($relationName);
            return $this;
        }

        $this->_onBeforeDelete();

        $query = Query::delete(array('id' => $this->_id), get_called_class());
        $count = $query->run()->rowCount();

        if ($count === 0)
        {
            throw new RecordNotFoundException($this->_id);
        }

        if (self::$_config->doForeignKeyWork)
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
     * @return $this
     */
    public function exclude($key)
    {
        foreach ((array) $key as $key)
        {
            static::$_excludedKeys[] = $key;
            unset($this->_attributes[$key]);
        }

        return $this;
    }

    /**
     * Tests if a model instance represented by its ID exists.
     *
     * @param mixed $m              Can be either the ID to test on or a condition array.
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
            catch (RecordNotFoundException $ex)
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
     * @return $this
     */
    public function filter($filter)
    {
        if (!isset(static::$_filters[$filter]))
        {
            throw new Exception('Filter "' . $filter . '" does not exist for model "' . get_class($this) . '".');
        }

        $this->_currentFilter = $filter;

        return $this;
    }

    /**
     * General finder method.
     *
     * This methods allows you to retrieve models from database. You can process several differents
     * finds. First parameter specifies find type:
     *
     * * "all": Retrieve several Model instances. Function will return an array;
     * * "first": Retrieve first found Model. Function will return a Model instance;
     * * "last": Retrieve last found Model. Function will return a Model instance;
     * * "count": Return number of found Models.
     * 
     * @param mixed $first Can be a ID to search on (shorthand for `Model::findByPK()`) or the find
     * type. For this to be considered as an ID, you must pass an integer or an array.
     * @param array $options An option array to find models.
     *
     * @return mixed
     *
     * @throws Exception When first parameter is invalid.
     */
    public static function find($first, $options = array())
    {
        // Find by primary key. It can be an array when using compound primary 
        // keys.
        if (is_int($first) || is_array($first))
        {
            return self::findByPK($first, $options);
        }

        // $first is a string. It can be "count", "first", "last" or "all".
        $multiplicity = null;
        switch ($first)
        {
            case 'all':
                $query = Query::select($options, get_called_class());
                $multiplicity = 'several';
                break;
            case 'count':
                $query = Query::count($options, get_called_class());
                $multiplicity = 'count';
                break;
            case 'first':
				$options['limit'] = 1;
                $query = Query::select($options, get_called_class());
                $multiplicity = 'one';
                break;
            case 'last':
				$options['limit'] = 1;

				if (isset($options['order_by']))
				{
					foreach ($options['order_by'] as $key => $order)
					{
						$options['order_by'][$key] = ($order == 'ASC' ? 'DESC' : 'ASC');
					}
				}
				else
				{
					$options['order_by'] = array('id' => 'DESC');
				}
                $query = Query::select($options, get_called_class());
                $multiplicity = 'one';
                break;
            default:
                throw new Exception('Invalid first parameter (' . $first .').');
                break;
        }

        return self::_processSqlQuery($query, $multiplicity, $options);
    }

    public static function findByPK($id, $options = array())
    {
        // Handles multiple primary keys, too.
        $options['conditions'] = array('id' => $id);

        // Fetch model.
		$query = Query::select($options, get_called_class());
        if (!$model = self::_processSqlQuery($query, 'one', $options))
        {
            throw new RecordNotFoundException($id);
        }

        return $model;
    }

	public static function findBySql($sql, $params = array(), $options = array())
	{
        return self::_processSqlQuery($sql, $params, 'several', $options);
	}

    public static function first($options = array())
    {
        return self::find('first', $options);
    }

    /**
     * This function checks that current object is linked to another.
     *
     * It is the matching of addTo() and removeFrom() methods.
     *
     * @param string $relation The name of the relation to check on.
     * @param mixed  $m         Linked object or object's ID.
     *
     * @return bool True if current object is linked to the other, false
     * otherwise.
     *
     * @see Model::addTo()
     * @see Model::removeFrom()
     */
    public function has($relation, $m)
    {
        $a = $this->$relation;

        if (!is_array($a)) { $a = array($a); }

        // We want to test by IDs, not by objects.
        if ($m instanceof Model)
        {
            $m = $m->id;
        }

        // $a is an array of Model instances
        // $o is a Model instance.
        foreach ($a as $o)
        {
            // I would like to use === operator but we are not sure that int IDs
            // really are integers.
            if ($o->id == $m)
            {
                return true;
            }
        }

        return false;
    }

    public static function init($config, $database)
    {
        self::$_config = $config;
        self::$_db     = $database;
    }

    public static function last($options = array())
    {
        return self::find('last', $options);
    }

    /**
     * Manually load linked model(s). Useful to reload the linked model(s).
     *
     * @param string $relation The relation to load.
     *
     * @return void
     */
    public function load($relation)
    {
        $this->_loadLinkedModel($relation);
    }

    public function modify($attributes)
    {
        $this->_hydrate($attributes);

        return $this;
    }

    /**
     * Get the relation identified by its name.
     *
     * @param $relationName The name of the relation.
     * @return object A Relationship object.
     */
    public static function relation($relationName, $relation = null)
    {
		if ($relation === null)
		{
			if (! isset(static::$_relations[$relationName]))
			{
				throw new Exception('Relation "' . $relationName . '" does not exist for class "' . get_called_class() . '".');
			}

			// If relation is not yet initlialized, do it.
			if (!is_object(static::$_relations[$relationName]))
			{
				static::$_relations[$relationName] = Relationship::forge($relationName, static::$_relations[$relationName], get_called_class());
			}

			// Return the Relationship object.
			return static::$_relations[$relationName];
		}
		else
		{
			static::$_relations[$relationName] = $relation;
		}
    }

    public static function remove($conditions = array())
    {
        $query = Query::delete($conditions, get_called_class());
        return $query->run()->rowCount();
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
    public function removeFrom($relation, $linkedModel)
    {
        $relation = static::relation($relation);

        if ($relation instanceof ManyMany)
        {
            if ($linkedModel instanceof Model)
            {
                if ($linkedModel->_id === null) { return; }

                $id = $linkedModel->_id;
            }
            else
            {
                $id = $linkedModel;
            }

            $query = Query::delete(
                array(
                    $relation->jm->from => $this->_id,
                    $relation->jm->to   => $id,
                ),
            $relation->jm->table);

            $query->run();
        }
    }

    /**
     * Saves the instance to the database. Will insert it if not yet created or
     * update otherwise.
     *
     * @return bool True if saving is OK, false otherwise.
     */
    public function save()
    {
        if ($this->_bIsDirty)
        {
            if ($this->_id === null)
            {
                $this->_insert();
            }
            else
            {
                $this->_update();
            }

            $this->_bIsDirty = false;
        }

        return $this;
    }

	/**
	 * This function makes pagination easier.
	 *
	 * @param $page int Page number. Min: 1.
	 * @param $nbItems int Number of items. Min: 1.
	 */
	public static function search($options, $page, $nbItems)
	{
		$page    = $page    >= 1 ? $page    : 1;
		$nbItems = $nbItems >= 1 ? $nbItems : 1;

		$options['limit']  = $nbItems;
		$options['offset'] = ($page - 1) * $nbItems;

		$res          = array();
		$res['count'] = static::count($options);
		$res['rows']  = static::all($options);

		return $res;
	}

    public static function table()
    {
        $a = explode('\\', get_called_class());

        return self::$_tables[array_pop($a)];
    }

    public function toArray()
    {
        $res = $this->attributes();

        return self::arrayToArray($res);
    }

    public static function wakeup()
    {
		// get_called_class() returns the namespaced class. So we have to parse
		// it.
		$a = explode('\\', get_called_class());
		$currentClass = array_pop($a);

		// Set defaults for model classes
		if ($currentClass != 'Model')
		{
            $suffix        = self::$_config->modelClassSuffix;
            $modelBaseName = $suffix ? strstr($currentClass, self::$_config->modelClassSuffix, true) : $currentClass;

            $tableName  = static::$_tableName  ?: call_user_func(self::$_config->classToTable, $modelBaseName);
            $primaryKey = static::$_primaryKey ?: self::$_config->primaryKey;

            // Columns are defined in model, perfect!
			if (static::$_columns)
			{
                $columns = static::$_columns;
			}
			// They are not, fetch them from database.
            else
            {
				$columns = self::$_db->query('SHOW COLUMNS FROM ' . $tableName)->fetchAll(\PDO::FETCH_COLUMN);

                // We do not want to insert primary key in
                // static::$_columns unless it is a compound key.
                if (is_string($primaryKey))
                {
                    unset($columns[$primaryKey]);
                }
            }

            $table = new Table($tableName, $primaryKey, $columns);
            $table->orderBy       = static::$_orderBy;
            $table->modelBaseName = $modelBaseName;

            self::$_tables[$currentClass] = $table;
		}
    }

    protected function _attr($attributeName)
    {
        if (func_num_args() === 1)
        {
            return $this->_attributes[$attributeName];
        }
        else
        {
            $newValue = func_get_arg(1);
            $oldValue = isset($this->_attributes[$attributeName]) ? $this->_attributes[$attributeName] : null;

            if ($newValue !== $oldValue)
            {
                $this->_attributes[$attributeName] = $newValue;
                $this->_bIsDirty = true;
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
                $conditions[$attribute] = $this->$attribute;
            }

            // Row already exists, we cannot insert a new row with these values.
            if (self::exists($conditions))
            {
                throw new Exception('Violate unique constraint: (' . implode(', ', $constraint) . ').');
            }
        }

        return $this;
    }

    protected function _onAfterDelete()
    {
    }

    protected function _onAfterInsert()
    {
    }

    protected function _onAfterLoad()
    {
    }

    protected function _onAfterUpdate()
    {
    }

    protected function _onBeforeDelete()
    {
    }

    protected function _onBeforeInsert()
    {
    }

    protected function _onBeforeLoad()
    {
    }

    protected function _onBeforeUpdate()
    {
    }

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
		$mClass = $relation->lm->class;

        if ($relation instanceof BelongsTo)
        {
            return $this->{$relation->cm->attribute} === NULL ? 0 : 1;
        }
        elseif ($relation instanceof HasOne || $relation instanceof HasMany)
        {
            $res = $mClass::count(array(
                'conditions' => array_merge($relation->conditions, array($relation->lm->attribute => $this->{$relation->cm->attribute})),
            ));
        }
        else // ManyMany
        {
			$reversed = $relation->reverse();

			$mClass::relation($reversed->name, $reversed);

			$conditions = array_merge(
				$reversed->conditions,
				array($reversed->name . '/' . $reversed->lm->attribute => $this->{$reversed->cm->attribute})
			);

			$res = $mClass::count(array(
                'conditions' => $conditions,
			));
        }

        return $res;
    }

    private function _deleteLinkedModel($relationName)
    {
        $relation = static::relation($relationName);

        if ($relation instanceof ManyMany)
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

            if ($relation instanceof ManyMany)
            {
                $relation->deleteJoinModel($this->_id);
            }

			if ($relation->onDeleteCascade)
			{
				$relation->deleteLinkedModel($this->_id);

				if ($relation instanceof HasOne)
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

    private function _hydrate($attributes, $useSetter = true)
    {
        // The following does not call setters if any.
		//$this->_attributes = $attributes + $this->_attributes;

        // This is not as efficient as above, but it is more consistent with what we want because it
        // will call setters.
        foreach ($attributes as $key => $value)
        {
            if ($useSetter)
            {
                $this->$key = $value;
            }
            else
            {
                $this->_attr($key, $value);
            }
        }
    }

    /**
     * Inserts the object in database.
     *
     * @return bool True on success, false otherwise.
     * @throws \PDOException if a database error occurs.
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
        $dateTimeFormat = self::$_config->databaseDateTimeFormat;

        foreach ($this->_attributes as $key => $value)
        {
            // Handle actual columns.
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
                if ($relation instanceof BelongsTo)
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
            $query = Query::insert(array(
                'fields' => array_keys($fields),
                'values' => array_values($fields)
            ), get_called_class());
            $query->run();

            // We fetch the ID.
            $this->_id = $query->insertId();

            // Process linked models.
            // We want to save linked models on cascade.
            foreach ($linkedModels as $a)
            {
                // Avoid multiple array accesses.
                $relation = $a['relation'];
                $object   = $a['object'];

                // Ignore if not Model instance.
                if ($relation instanceof HasOne)
                {
                    if($object instanceof Model)
                    {
                        $object->{$relation->lm->attribute} = $this->_id;
                        $object->save();
                    }
                }
                elseif ($relation instanceof HasMany)
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

					$query = Query::insert(array(
                        'fields' => array($relation->jm->from, $relation->jm->to),
                        'values' => $values
                    ), $relation->jm->table);

                    $query->run();
                }
            }
        }
        catch (DatabaseException $ex)
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
     */
    private function _load($row)
    {
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
                $mClass  = $relation->lm->class;

                if ($relation instanceof BelongsTo || $relation instanceof HasOne)
                {
                    // $value is an array of attributes. The attributes of the linked model 
                    // instance.
                    // But it *might* be an array of arrays of attributes. For instance, when 
                    // relation is defined as a Has One relation but actually is a Has Many in 
                    // database. In that case, SQL query would return several rows for this relation 
                    // and we would result with $value to be an array of arrays.

                    // Array of arrays ==> array of attribute.
                    if (isset($value[0])) { $value = $value[0]; }

                    $o = new $mClass();
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
                            $o = new $mClass();
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

        if (self::$_config->convertDateToObject)
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

        return $this;
    }

    /**
     * Loads a model defined in model relations array.
     *
     * @param string $name The relations array entry.
     *
     * @return Model|array|null
     */
    private function _loadLinkedModel($relationName)
    {
        $relation = static::relation($relationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        if ($this->_id === null)
        {
            if ($relation instanceof BelongsTo || $relation instanceof HasOne)
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
		$mClass = $relation->lm->class;

        if ($relation instanceof BelongsTo || $relation instanceof HasOne)
        {
            $res = $mClass::first(array(
                'conditions' => array_merge($relation->conditions, array($relation->lm->attribute => $this->{$relation->cm->attribute})),
				'order_by'	 => $relation->order,
                'filter'     => $relation->filter,
            ));
        }
        elseif ($relation instanceof HasMany)
        {
            $res = $mClass::all(array(
                'conditions' => array_merge($relation->conditions, array($relation->lm->attribute => $this->{$relation->cm->attribute})),
				'order_by'	 => $relation->order,
                'filter'     => $relation->filter,
            ));
        }
        else // ManyMany
        {
			$reversed = $relation->reverse();

			$mClass::relation($reversed->name, $reversed);

			$conditions = array_merge(
				$reversed->conditions,
				array($reversed->name . '/' . $reversed->lm->attribute => $this->{$reversed->cm->attribute})
			);

			$res = $mClass::all(array(
                'conditions' => $conditions,
				'order_by'	 => $relation->order,
                'filter'     => $relation->filter,
			));
        }

        $this->_attr($relationName, $res);
    }


    private static function _processSqlQuery($query, $multiplicity, $options)
    {
        $res   = null;

        $query->run();

        switch ($multiplicity)
		{
            case 'count':
                $res = $query->res();
                break;

            case 'one':
                $row = $query->row();

                if ($row)
				{
                    $res = new static(null, $options);
                    $res->_load($row);
                }
                break;

            case 'several':
                $rows = $query->all();
                $res  = array();

                foreach ($rows as $row)
				{
                    $model = new static(null, $options);
                    $model->_load($row);

                    $res[] = $model;
                }
                break;
            default:
                throw new Exception('Unknown multiplicity: "' . $multiplicity . '".');
                break;
        }

        return $res;
    }

    private function _setDefaultValues()
    {
        foreach (static::$_defaultValues as $key => $value)
		{
            $this->_attributes[$key] = $value;
        }
    }

    /**
     * Updates a instance.
     *
     * @throws Exception if a database error occurs.
     * @return $this
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
        $dateTimeFormat = self::$_config->databaseDateTimeFormat;

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
                if ($relation instanceof BelongsTo)
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
            $query = Query::update(array(
                'fields' => array_keys($fields),
                'values' => array_values($fields),
                'conditions' => array('id' => $this->_id)
            ), get_called_class());
            $query->run();

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
                if ($relation instanceof HasOne)
                {
                    if($object instanceof Model)
                    {
                        $object->{$relation->lm->attribute} = $this->_id;
                        $object->save();
                    }
                }
                elseif ($relation instanceof HasMany)
                {
                    // Ignore if not Model instance.
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $object as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
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
                    //  - save instances on cascade.
                    //  - link these instances with current model via the
                    //  relation join table. To do this:
                    //      1) Delete all rows of join table that concerns
                    //      current model;
                    //      2) Insert new rows.

                    // Remove all rows from join table. (Easier this way.)
					$query = Query::delete(array($relation->jm->from => $this->_id), $relation->jm->table);
                    $query->run();

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

					$query = Query::insert(array(
                        'fields' => array($relation->jm->from, $relation->jm->to),
                        'values' => $values
                    ), $relation->jm->table);

                    $query->run();
                }
            }
        }
        catch (DatabaseException $ex)
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
