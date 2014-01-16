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
    protected static $_oDb;

    /**
     * Contains configuration.
     *
     * @var \SimpleAR\Config
     */      
    protected static $_oConfig;

    /**
     * The name of the database table this model is associated with.
     *
     * @var string
     */
    protected static $_sTableName;

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
    protected static $_mPrimaryKey;

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
     * corresponding to $_aColumns keys.
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
     * {@see \SimpleAR\Model::$_aDefaultValues} array. These value
     * will be used for model instance insertion in DB.
     *
     * @var array
     */
    protected static $_aColumns = array();

    /**
     * Attributes' default values array.
     *
     * Example:
     * ```php
     *  protected $_aDefaultValues = array(
     *      'myAttribute' => 12,
     *  );
     * ```
     * If nothing is set for 'myAttribute' before saving, value 12 will be
     * inserted.
     *
     * @var array
     */
    protected static $_aDefaultValues = array();

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
     * \SimpleAR\Model::$_aColumns).
     */
    protected static $_aFilters = array();

    /**
     * An array that allows another way to filter output.
     *
     * All fields present in this array will be excluded from the output. A
     * difference operation is made between this array and $_aColumns
     * before output.
     *
     * You can use exclude() function to add fields to exclude from output.
     *
     * @var array
     */
    protected static $_aExcludedKeys = array();

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
    protected static $_aUniqueConstraints = array();

    /**
     * This array defines order of rows when fetching multiple rows of this
     * model. Typical example: search function.
     *
     * @var array
     */
    protected static $_aOrderBy = array();

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
     *      protected $_aRelations = array(
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
     *      protected $_aRelations = array(
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
    protected static $_aRelations = array();

    /**
     * The model instance's ID.
     *
     * This is the primary key of the model. It contains the value of DB field
     * name corresponding to $_mPrimaryKey.
     *
     * @var mixed
     */
    protected $_mId;

    /**
     * The model instance's attribute list.
     *
     * It contains all *used* model instance attributes of the model. This array
     * is constructed in serveral places in the code. Let's review them!
     *
     * * When a row is fetched from database, an Model instance is created and its
     * $_aAttributes array is filled with all selected fields and their values;
     * * When you access to a relation: Linked models are fetched from database
     * and stored in this array;
     * * When you set an attribute that is not in this array yet;
     * * When access to a *count* attribute.
     *
     * @var array
     */
    protected $_aAttributes = array();

    /**
     * This attribute is used to decide if model instance really has to be
     * saved when using `save()` method. For instance, if no attribute has been
     * modified, there is no need to execute an UPDATE SQL query.
     *
     * @var bool
     */
    protected $_bIsDirty = false;

    /**
     * This attribute contains the current used filter.
     *
     * @var string
     */
    protected $_sCurrentFilter;

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
    private static $_aTables = array();

    /**
     * Constructor.
     *
     * The constructor methods is used to create a new Model instance from
     * scratch. Attribute array and some extra option can be passed it in.
     *
     * Besides, constructor will set default values if any specified in
     * `Model::$_aDefaultValues`.
     *
     * @param array $aAttributes Array of attributes to fill the new instance
     * with. The `Model::_fill()` method will be used for this.
     *
     * @param array $aOptions. An array option to extra configure the new
     * instance. It can contain following entries:
     *
     *  * "filter": An optional filter to apply to the model.
     *
     * @see SimpleAR\Model::_fill()
     * @see SimpleAR\Model::_setDefaultValues()
     */
    public function __construct($aAttributes = array(), $aOptions = array())
    {
        if (isset($aOptions['filter']))
        {
            $this->filter($aOptions['filter']);
        }

		if ($aAttributes)
		{
			$this->_fill($aAttributes);
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
     * 2. Is in attribute array (`Model::$_aAttributes`). If there, return it;
     * 3. Is there a method called `get_<attribute name>()`? If yes, fire it and
     * return its result;
     * 4. Is it a relation (in `Model::$_aRelations`)? If yes, load linked
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
     *  echo $oMyCompany->{'#products'};
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
            return $this->_mId;
        }

        // Classic attribute.
        if (isset($this->_aAttributes[$s]) || array_key_exists($s, $this->_aAttributes))
        { 
            return $this->_aAttributes[$s];
        }

        if (method_exists($this, 'get_' . $s))
        {
            return call_user_func(array($this, 'get_' . $s));
        } 

        // Relation.
        // Will arrive here maximum once per relation because when a relation is
        // loaded, an attribute named as the relation is appended to $this->_aAttributes.
        // So, it would return it right above.
        if (isset(static::$_aRelations[$s]))
        {
            $this->_loadLinkedModel($s);

            if (method_exists($this, 'get_' . $s))
            {
                return call_user_func(array($this, 'get_' . $s));
            }

            return $this->_aAttributes[$s];
        }

        // Count. Rare case, that is why it is at the end. 
		if ($s[0] === '#')
		{
			$sBaseName = substr($s, 1);

            if (method_exists($this, 'count_' . $sBaseName))
            {
                return $this->_aAttributes[$s] = call_user_func(array($this, 'count_' . $sBaseName));
            }

            if (isset($this->_aAttributes[$sBaseName]))
            {
                return $this->_aAttributes[$s] = count($this->_aAttributes[$sBaseName]);
            }

            if (isset(static::$_aRelations[$sBaseName]))
            {
                return $this->_aAttributes[$s] = $this->_countLinkedModel($sBaseName);
			}
		}


        $aTrace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $s .
            ' in ' . $aTrace[0]['file'] .
            ' on line ' . $aTrace[0]['line'],
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
     * @param string $sName  The property name.
     * @param mixed  $mValue The property value.
     *
     * @return void
     */
    public function __set($sName, $mValue)
    {
        if (method_exists($this, 'set_' . $sName))
        {
            call_user_func(array($this, 'set_' . $sName), $mValue);
        }
        else
        {
            $this->_attr($sName, $mValue);
        }

        return;

        $aTrace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $sName .
            ' in ' . $aTrace[0]['file'] .
            ' on line ' . $aTrace[0]['line'],
            E_USER_NOTICE
		);
    }

    /**
     * This function allow to only *link* one model from a relation
     * without *unlink* all of the linked models (that would have been the case
     * when directly assigning the relation content via `=`.
     *
     * ```php
     * $oUser->addTo('applications', 12);
     * ```
     *
     * or
     *
     * ```php
     * $oUser->addTo('applications', $oJobOffer);
     * ```
     *
     * @param string    $sRelation    The relation name.
     * @param int|Model $mLinkedModel What to add.
     *
     * @return void.
     */
    public function addTo($sRelation, $mLinkedModel)
    {
        $oRelation = static::relation($sRelation);

        if ($oRelation instanceof ManyMany)
        {
            if ($mLinkedModel instanceof Model)
            {
                $mLinkedModel->save();
                $mId = $mLinkedModel->_mId;
            }
            else
            {
                $mId = $mLinkedModel;
            }

            $oQuery = Query::insert(array(
                'fields' => array($oRelation->jm->from, $oRelation->jm->to),
                'values' => array($this->_mId, $mId),
            ), $oRelation->jm->table);

            $oQuery->run();
        }
    }

    /**
     * Return several Model instances according to an option array.
     *
     * This function is an alias for:
     *  ```php
     * Model::find('all', $aOptions);
     *  ```
     * @param array $aOptions An option array.
     * @return array
     *
     * @see SimpleAR\Model::find()
     */
    public static function all($aOptions = array())
    {
        return self::find('all', $aOptions);
    }

    public static function arrayToArray($aArray)
    {
        foreach ($aArray as &$m)
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

        return $aArray;
    }

    /**
     * Return attribute array plus the object ID.
     *
     * @return array The instance attributes and its ID.
     */
    public function attributes()
    {
        return array('id' => $this->_mId) + $this->_aAttributes;
    }

    /**
     * Very useful function to get an array of the attributes to select from
     * database. Attributes are values of $_aColumns.
     *
     * @param string $sFilter Optional filter that prevents us to fetch all
     * attribute from the table.
     *
     * @param string $sTableAlias The alias to use to designate the table.
     * @param string $sResAlias   The alias to use to tidy resulting row up.
     *
     * @note: it will *not* throw any exception if filter does not exist; instead,
     * it will not use any filter.
     *
     * @return array
     */
    public static function columnsToSelect($sFilter = null, $sTableAlias = '', $sResAlias = '')
    {
        $oTable = static::table();
        $aRes   = array();

        $aColumns = ($sFilter !== null && isset(static::$_aFilters[$sFilter]))
            ? array_intersect_key($oTable->columns, array_flip(static::$_aFilters[$sFilter]))
            : $oTable->columns
            ;

        // Include primary key to columns to fetch. Useful only for simple
        // primary keys.
        if ($oTable->isSimplePrimaryKey)
        {
            $aColumns['id'] = $oTable->primaryKey;
        }

        // If a table alias is given, add a dot to respect SQL syntax and to not
        // worry about it in following foreach loop.
        if ($sTableAlias) { $sTableAlias .= '.'; }
        if ($sResAlias)   { $sResAlias   .= '.'; }

        foreach ($aColumns as $sAttribute => $sColumn)
        {
            $aRes[] = $sTableAlias . $sColumn . ' AS `' . $sResAlias . $sAttribute . '`';
        }

        return $aRes;
    }

    /**
     * Return count of Model instances according to an option array.
     *
     * This function is an alias for:
     *  ```php
     * Model::find('count', $aOptions);
     *  ```
     * @param array $aOptions An option array.
     * @return array
     *
     * @see SimpleAR\Model::find()
     */
    public static function count($aOptions = array())
    {
        return self::find('count', $aOptions);
    }

    /**
     * This function creates a new Model instance and inserts it into database.
     *
     * This is just a shorthand for:
     *  ```php
     *  $o = new static($aAttributes);
     *  return $o->save();
     *  ```
     *
     * @param array $aAttributes Array of attributes to fill the new instance
     * with.
     *
     * @return $this
     *
     * @see SimpleAR\Model::__construct()
     * @see SimpleAR\Model::save()
     */
    public static function create($aAttributes)
    {
        $o = new static($aAttributes);
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
    public function delete($sRelationName = null)
    {
        if ($sRelationName !== null)
        {
            $this->_deleteLinkedModel($sRelationName);
            return $this;
        }

        $this->_onBeforeDelete();

        $oQuery = Query::delete(array('id' => $this->_mId), get_called_class());
        $iCount = $oQuery->run()->rowCount();

        if ($iCount === 0)
        {
            throw new RecordNotFoundException($this->_mId);
        }

        if (self::$_oConfig->doForeignKeyWork)
        {
            $this->_deleteLinkedModels();
        }

        $this->_onAfterDelete();

        $this->_mId = null;

        return $this;
    }

    /**
     * Adds one or several fields in excluding filter array.
     *
     * @param string|array $mKeys The key(s) to add in the array.
     * @return $this
     */
    public function exclude($mKey)
    {
        foreach ((array) $mKey as $sKey)
        {
            static::$_aExcludedKeys[] = $sKey;
            unset($this->_aAttributes[$sKey]);
        }

        return $this;
    }

    /**
     * Tests if a model instance represented by its ID exists.
     *
     * @param mixed $m              Can be either the ID to test on or a condition array.
     * @param bool  $bByPrimaryKey  Should $m be considered as a primary key or as a condition
     * array?
     *
     * @return bool True if it exists, false otherwise.
     */
    public static function exists($m, $bByPrimaryKey = true)
    {
        // Classic exists(): By primary key.
        if ($bByPrimaryKey)
        {
            try
            {
                static::findByPK($m);
                return true;
            }
            catch (RecordNotFoundException $oEx)
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
     * @param array|string $mFilter The filter array that contains all fields we want to
     * keep when output OR the name of the filter we want to apply.
     * @return $this
     */
    public function filter($sFilter)
    {
        if (!isset(static::$_aFilters[$sFilter]))
        {
            throw new Exception('Filter "' . $sFilter . '" does not exist for model "' . get_class($this) . '".');
        }

        $this->_sCurrentFilter = $sFilter;

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
     * @param mixed $mFirst Can be a ID to search on (shorthand for `Model::findByPK()`) or the find
     * type. For this to be considered as an ID, you must pass an integer or an array.
     * @param array $aOptions An option array to find models.
     *
     * @return mixed
     *
     * @throws Exception When first parameter is invalid.
     */
    public static function find($mFirst, $aOptions = array())
    {
        // Find by primary key. It can be an array when using compound primary 
        // keys.
        if (is_int($mFirst) || is_array($mFirst))
        {
            return self::findByPK($mFirst, $aOptions);
        }

        // $mFirst is a string. It can be "count", "first", "last" or "all".
        $sMultiplicity = null;
        switch ($mFirst)
        {
            case 'all':
                $oQuery = Query::select($aOptions, get_called_class());
                $sMultiplicity = 'several';
                break;
            case 'count':
                $oQuery = Query::count($aOptions, get_called_class());
                $sMultiplicity = 'count';
                break;
            case 'first':
				$aOptions['limit'] = 1;
                $oQuery = Query::select($aOptions, get_called_class());
                $sMultiplicity = 'one';
                break;
            case 'last':
				$aOptions['limit'] = 1;

				if (isset($aOptions['order']))
				{
					foreach ($aOptions['order'] as $sKey => $sOrder)
					{
						$aOptions['order'][$sKey] = ($sOrder == 'ASC' ? 'DESC' : 'ASC');
					}
				}
				else
				{
					$aOptions['order'] = array('id' => 'DESC');
				}
                $oQuery = Query::select($aOptions, get_called_class());
                $sMultiplicity = 'one';
                break;
            default:
                throw new Exception('Invalid first parameter (' . $mFirst .').');
                break;
        }

        return self::_processSqlQuery($oQuery, $sMultiplicity, $aOptions);
    }

    public static function findByPK($mId, $aOptions = array())
    {
        // Handles multiple primary keys, too.
        $aOptions['conditions'] = array('id' => $mId);

        // Fetch model.
		$oQuery = Query::select($aOptions, get_called_class());
        if (!$oModel = self::_processSqlQuery($oQuery, 'one', $aOptions))
        {
            throw new RecordNotFoundException($mId);
        }

        return $oModel;
    }

	public static function findBySql($sSql, $aParams = array(), $aOptions = array())
	{
        return self::_processSqlQuery($sSql, $aParams, 'several', $aOptions);
	}

    public static function first($aOptions = array())
    {
        return self::find('first', $aOptions);
    }

    public static function init($oConfig, $oDatabase)
    {
        self::$_oConfig = $oConfig;
        self::$_oDb     = $oDatabase;
    }

    public static function last($aOptions = array())
    {
        return self::find('last', $aOptions);
    }

    /**
     * Manually load linked model(s). Useful to reload the linked model(s).
     *
     * @param string $sRelation The relation to load.
     *
     * @return void
     */
    public function load($sRelation)
    {
        $this->_loadLinkedModel($sRelation);
    }

    public function modify($aAttributes)
    {
        $this->_fill($aAttributes);

        return $this;
    }

    /**
     * Get the relation identified by its name.
     *
     * @param $sRelationName The name of the relation.
     * @return object A Relationship object.
     */
    public static function relation($sRelationName, $oRelation = null)
    {
		if ($oRelation === null)
		{
			if (! isset(static::$_aRelations[$sRelationName]))
			{
				throw new Exception('Relation "' . $sRelationName . '" does not exist for class "' . get_called_class() . '".');
			}

			// If relation is not yet initlialized, do it.
			if (!is_object(static::$_aRelations[$sRelationName]))
			{
				static::$_aRelations[$sRelationName] = Relationship::forge($sRelationName, static::$_aRelations[$sRelationName], get_called_class());
			}

			// Return the Relationship object.
			return static::$_aRelations[$sRelationName];
		}
		else
		{
			static::$_aRelations[$sRelationName] = $oRelation;
		}
    }

    public static function remove($aConditions = array())
    {
        $oQuery = Query::delete($aConditions, get_called_class());
        return $oQuery->run()->rowCount();
    }

    /**
     * This function allow to only *unlink* one model from a relation
     * without unlink all of the linked models (that would have been the case
     * when directly assigning the relation content via `=`.
     *
     * ```php
     * $oUser->removeFrom('applications', 12);
     * ```
     *
     * or
     *
     * ```php
     * $oUser->removeFrom('applications', $oJobOffer);
     * ```
     *
     * @param string    $sRelation    The relation name.
     * @param int|Model $mLinkedModel What to remove.
     *
     * @return void.
     */
    public function removeFrom($sRelation, $mLinkedModel)
    {
        $oRelation = static::relation($sRelation);

        if ($oRelation instanceof ManyMany)
        {
            if ($mLinkedModel instanceof Model)
            {
                if ($mLinkedModel->_mId === null) { return; }

                $mId = $mLinkedModel->_mId;
            }
            else
            {
                $mId = $mLinkedModel;
            }

            $oQuery = Query::delete(
                array(
                    $oRelation->jm->from => $this->_mId,
                    $oRelation->jm->to   => $mId,
                ),
            $oRelation->jm->table);

            $oQuery->run();
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
            if ($this->_mId === null)
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
	 * @param $iPage int Page number. Min: 1.
	 * @param $iNbItems int Number of items. Min: 1.
	 */
	public static function search($aOptions, $iPage, $iNbItems)
	{
		$iPage    = $iPage    >= 1 ? $iPage    : 1;
		$iNbItems = $iNbItems >= 1 ? $iNbItems : 1;

		$aOptions['limit']  = $iNbItems;
		$aOptions['offset'] = ($iPage - 1) * $iNbItems;

		$aRes          = array();
		$aRes['count'] = static::count($aOptions);
		$aRes['rows']  = static::all($aOptions);

		return $aRes;
	}

    public static function table()
    {
        $a = explode('\\', get_called_class());

        return self::$_aTables[array_pop($a)];
    }

    public function toArray()
    {
        $aRes = $this->attributes();

        return self::arrayToArray($aRes);
    }

    public static function wakeup()
    {
		// get_called_class() returns the namespaced class. So we have to parse
		// it.
		$a = explode('\\', get_called_class());
		$sCurrentClass = array_pop($a);

		// Set defaults for model classes
		if ($sCurrentClass != 'Model')
		{
            $sSuffix        = self::$_oConfig->modelClassSuffix;
            $sModelBaseName = $sSuffix ? strstr($sCurrentClass, self::$_oConfig->modelClassSuffix, true) : $sCurrentClass;

            $sTableName  = static::$_sTableName  ?: call_user_func(self::$_oConfig->classToTable, $sModelBaseName);
            $mPrimaryKey = static::$_mPrimaryKey ?: self::$_oConfig->primaryKey;

            // Columns are defined in model, perfect!
			if (static::$_aColumns)
			{
                $aColumns = static::$_aColumns;
			}
			// They are not, fetch them from database.
            else
            {
				$aColumns = self::$_oDb->query('SHOW COLUMNS FROM ' . $sTableName)->fetchAll(\PDO::FETCH_COLUMN);

                // We do not want to insert primary key in
                // static::$_aColumns unless it is a compound key.
                if (is_string($mPrimaryKey))
                {
                    unset($aColumns[$mPrimaryKey]);
                }
            }

            $oTable = new Table($sTableName, $mPrimaryKey, $aColumns);
            $oTable->orderBy       = static::$_aOrderBy;
            $oTable->modelBaseName = $sModelBaseName;

            self::$_aTables[$sCurrentClass] = $oTable;
		}
    }

    protected function _attr($sAttributeName)
    {
        if (func_num_args() === 1)
        {
            return $this->_aAttributes[$sAttributeName];
        }
        else
        {
            $mNewValue = func_get_arg(1);
            $mOldValue = isset($this->_aAttributes[$sAttributeName]) ? $this->_aAttributes[$sAttributeName] : null;

            if ($mNewValue !== $mOldValue)
            {
                $this->_aAttributes[$sAttributeName] = $mNewValue;
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
        foreach (static::$_aUniqueConstraints as $aConstraint)
        {
            // Construct conditions array we will use to check if a row exists.
            $aConditions = array();
            foreach ($aConstraint as $sAttribute)
            {
                $aConditions[$sAttribute] = $this->$sAttribute;
            }

            // Row already exists, we cannot insert a new row with these values.
            if (self::exists($aConditions))
            {
                throw new Exception('Violate unique constraint: (' . implode(', ', $aConstraint) . ').');
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

    private function _countLinkedModel($sRelationName)
    {
        $oRelation = static::relation($sRelationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        if ($this->_mId === null)
        {
			return 0;
        }

        // Our object is already saved. It has an ID. We are going to
        // fetch potential linked objects from DB.
		$iRes     = 0;
		$sLMClass = $oRelation->lm->class;

        if ($oRelation instanceof BelongsTo)
        {
            return $this->{$oRelation->cm->attribute} === NULL ? 0 : 1;
        }
        elseif ($oRelation instanceof HasOne || $oRelation instanceof HasMany)
        {
            $iRes = $sLMClass::count(array(
                'conditions' => array_merge($oRelation->conditions, array($oRelation->lm->attribute => $this->{$oRelation->cm->attribute})),
            ));
        }
        else // ManyMany
        {
			$oReversed = $oRelation->reverse();

			$sLMClass::relation($oReversed->name, $oReversed);

			$aConditions = array_merge(
				$oReversed->conditions,
				array($oReversed->name . '/' . $oReversed->lm->attribute => $this->{$oReversed->cm->attribute})
			);

			$iRes = $sLMClass::count(array(
                'conditions' => $aConditions,
			));
        }

        return $iRes;
    }

    private function _deleteLinkedModel($sRelationName)
    {
        $oRelation = static::relation($sRelationName);

        if ($oRelation instanceof ManyMany)
        {
            $oRelation->deleteJoinModel($this->_mId);
        }

        $oRelation->deleteLinkedModel($this->_mId);
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
     * @link ModelAbstract::_aRelations for more information on relations
     * between models.
     */
    private function _deleteLinkedModels()
    {
        foreach (static::$_aRelations as $sName => $m)
        {
            $oRelation = static::relation($sName);

            if ($oRelation instanceof ManyMany)
            {
                $oRelation->deleteJoinModel($this->_mId);
            }

			if ($oRelation->onDeleteCascade)
			{
				$oRelation->deleteLinkedModel($this->_mId);

				if ($oRelation instanceof HasOne)
				{
					$this->_aAttributes[$sName] = null;
				}
				else // HasMany || ManyMany
				{
					$this->_aAttributes[$sName] = array();
				}
			}
        }
    }

    private function _fill($aAttributes)
    {
        // The following does not call setters if any.
		//$this->_aAttributes = $aAttributes + $this->_aAttributes;

        // This is not as efficient as above, but it is more consistent with what we want because it
        // will call setters.
        foreach ($aAttributes as $sKey => $mValue)
        {
            $this->$sKey = $mValue;
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

        $oTable   = static::table();
        $aColumns = $oTable->columns;

        // Keys will be attribute names; Values will be attributes values.
        $aFields = array();

        // Will contains LM to save in cascade.
        $aLinkedModels = array();

        // Avoid retrieving config option several times.
        $sDateTimeFormat = self::$_oConfig->databaseDateTimeFormat;

        foreach ($this->_aAttributes as $sKey => $mValue)
        {
            // Handle actual columns.
            if (isset($aColumns[$sKey]))
            {
                // Transform DateTime object into a database-formatted string.
                if ($mValue instanceof \DateTime)
                {
                    $mValue = $mValue->format($sDateTimeFormat);
                }

                $aFields[$sKey] = $mValue;
                continue;
            }

            // Handle linked models.
            // $mValue can be:
            // - a Model instance not saved yet (BelongsTo or HasOne relations);
            // - a Model instance ID (BelongsTo or HasOne relations) (Not
            // implemented).
            //
            // - a Model instance array (HasMany or ManyMany relations);
            // - a Model instance ID array (HasMany or ManyMany relations).
            if (isset(static::$_aRelations[$sKey]))
            {
                $oRelation = static::relation($sKey);

                // If it is linked by a BelongsTo instance, update local field.
                if ($oRelation instanceof BelongsTo)
                {
                    // Save in cascade.
                    $mValue->save();

                    // We use array_merge() and array_combine() in order to handle composed keys.
                    $aFields = array_merge($aFields, array_combine((array) $oRelation->cm->attribute, (array) $mValue->id));
                }
                // Otherwise, handle it later (After CM insert, actually, because we need CM ID).
                else
                {
                    $aLinkedModels[] = array('relation' => static::relation($sKey), 'object' => $mValue);
                }
            }
        }

        try
        {
            $oQuery = Query::insert(array(
                'fields' => array_keys($aFields),
                'values' => array_values($aFields)
            ), get_called_class());
            $oQuery->run();

            // We fetch the ID.
            $this->_mId = $oQuery->insertId();

            // Process linked models.
            // We want to save linked models on cascade.
            foreach ($aLinkedModels as $a)
            {
                // Avoid multiple array accesses.
                $oRelation = $a['relation'];
                $mObject   = $a['object'];

                // Ignore if not Model instance.
                if ($oRelation instanceof HasOne)
                {
                    if($mObject instanceof Model)
                    {
                        $mObject->{$oRelation->lm->attribute} = $this->_mId;
                        $mObject->save();
                    }
                }
                elseif ($oRelation instanceof HasMany)
                {
                    // Ignore if not Model instance.
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $mObject as $o)
                    {
                        if ($o instanceof Model)
                        {
                            $o->{$oRelation->lm->attribute} = $this->_mId;
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
                    $aValues = array();
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $mObject as $m)
                    {
                        if ($m instanceof Model)
                        {
                            $m->save();
                            $aValues[] = array($this->_mId, $m->_mId);
                        }
                        // Else we consider this is an ID.
                        else
                        {
                            $aValues[] = array($this->_mId, $m);
                        }
                    }

					$oQuery = Query::insert(array(
                        'fields' => array($oRelation->jm->from, $oRelation->jm->to),
                        'values' => $aValues
                    ), $oRelation->jm->table);

                    $oQuery->run();
                }
            }
        }
        catch (DatabaseException $oEx)
        {
            throw $oEx;
        }
        catch(Exception $oEx)
        {
            throw new Exception('Error inserting ' . get_class($this) . ' in database.', 0, $oEx);
        }

        // Other actions.
        $this->_onAfterInsert();

        return $this;
    }

    /**
     * Loads the model instance from the database.
     *
     * @param array $aRow It is the array directly given by the
     * \PDOStatement:fetch() function. Every field contained in this  parameter
     * correspond to attribute name, not to column's (@see columnsToSelect()).
     *
     * @return $this
     */
    private function _load($aRow)
    {
        $this->_onBeforeLoad();

        $oTable = static::table();

        // We set our object ID.
        if ($oTable->isSimplePrimaryKey)
        {
            $this->_mId = $aRow['id'];
            unset($aRow['id']);
        }
        else
        {
            $this->_mId = array();

            foreach ($oTable->primaryKey as $sAttribute)
            {
                $this->_mId[] = $aRow[$sAttribute];
                //unset($aRow[$sKey]);
            }
        }

        // Eager load.
        if (isset($aRow['_WITH_']))
        {
            foreach ($aRow['_WITH_'] as $sRelation => $aValue)
            {
                $oRelation = static::relation($sRelation);
                $sLMClass  = $oRelation->lm->class;

                if ($oRelation instanceof BelongsTo || $oRelation instanceof HasOne)
                {
                    // $aValue is an array of attributes. The attributes of the linked model 
                    // instance.
                    // But it *might* be an array of arrays of attributes. For instance, when 
                    // relation is defined as a Has One relation but actually is a Has Many in 
                    // database. In that case, SQL query would return several rows for this relation 
                    // and we would result with $aValue to be an array of arrays.

                    // Array of arrays ==> array of attribute.
                    if (isset($aValue[0])) { $aValue = $aValue[0]; }

                    $o = new $sLMClass();
                    $o->_load($aValue);

                    if ($o->id !== null)
                    {
                        $this->_attr($sRelation, $o);
                    }
                }
                else
                {
                    // $aValue is an array of arrays. These subarrays contain attributes of linked 
                    // models.
                    // But $aValue can directly be an associative array (if SQL query returned only 
                    // one row). We have to check this, then.

                    $a = array();

                    if ($aValue)
                    {
                        // $aValue is an attribute array.
                        // Array of attributes ==> array of arrays.
                        if (! isset($aValue[0])) { $aValue = array($aValue); }

                        foreach ($aValue as $aAttributes)
                        {
                            $o = new $sLMClass();
                            $o->_load($aAttributes);

                            if ($o->id !== null)
                            {
                                $a[] = $o;
                            }
                        }
                    }

                    $this->_attr($sRelation, $a);
                }
            }

            unset($aRow['_WITH_']);
        }

        $this->_fill($aRow);

        if (self::$_oConfig->convertDateToObject)
        {
            foreach ($this->_aAttributes as $sKey => &$mValue)
            {
                // We test that is a string because setters might have been
                // called from within _fill() so we cannot be sure of what
                // $mValue is.
                //
                // strpos call <=> $sKey.startsWith('date')
                if (is_string($mValue) && strpos($sKey, 'date') === 0)
                {
                    // Do not process "NULL-like" values (0000-00-00 or 0000-00-00 00:00). It would 
                    // cause strange values.
                    // @see http://stackoverflow.com/questions/10450644/how-do-you-explain-the-result-for-a-new-datetime0000-00-00-000000
                    $mValue =  $mValue === '0000-00-00'
                            || $mValue === '0000-00-00 00:00:00'
                            || $mValue === null
                            ? null
                            : new DateTime($mValue)
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
     * @param string $sName The relations array entry.
     *
     * @return Model|array|null
     */
    private function _loadLinkedModel($sRelationName)
    {
        $oRelation = static::relation($sRelationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        if ($this->_mId === null)
        {
            if ($oRelation instanceof BelongsTo || $oRelation instanceof HasOne)
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
        $mRes	  = null;
		$sLMClass = $oRelation->lm->class;

        if ($oRelation instanceof BelongsTo || $oRelation instanceof HasOne)
        {
            $mRes = $sLMClass::first(array(
                'conditions' => array_merge($oRelation->conditions, array($oRelation->lm->attribute => $this->{$oRelation->cm->attribute})),
				'order'		 => $oRelation->order,
                'filter'     => $oRelation->filter,
            ));
        }
        elseif ($oRelation instanceof HasMany)
        {
            $mRes = $sLMClass::all(array(
                'conditions' => array_merge($oRelation->conditions, array($oRelation->lm->attribute => $this->{$oRelation->cm->attribute})),
				'order'		 => $oRelation->order,
                'filter'     => $oRelation->filter,
            ));
        }
        else // ManyMany
        {
			$oReversed = $oRelation->reverse();

			$sLMClass::relation($oReversed->name, $oReversed);

			$aConditions = array_merge(
				$oReversed->conditions,
				array($oReversed->name . '/' . $oReversed->lm->attribute => $this->{$oReversed->cm->attribute})
			);

			$mRes = $sLMClass::all(array(
                'conditions' => $aConditions,
				'order'		 => $oRelation->order,
                'filter'     => $oRelation->filter,
			));
        }

        $this->_attr($sRelationName, $mRes);
    }


    private static function _processSqlQuery($oQuery, $sMultiplicity, $aOptions)
    {
        $mRes   = null;

        $oQuery->run();

        switch ($sMultiplicity)
		{
            case 'count':
                $mRes = $oQuery->res();
                break;

            case 'one':
                $aRow = $oQuery->row();

                if ($aRow)
				{
                    $mRes = new static(null, $aOptions);
                    $mRes->_load($aRow);
                }
                break;

            case 'several':
                $aRows = $oQuery->all();
                $mRes  = array();

                foreach ($aRows as $aRow)
				{
                    $oModel = new static(null, $aOptions);
                    $oModel->_load($aRow);

                    $mRes[] = $oModel;
                }
                break;
            default:
                throw new Exception('Unknown multiplicity: "' . $sMultiplicity . '".');
                break;
        }

        return $mRes;
    }

    private function _setDefaultValues()
    {
        foreach (static::$_aDefaultValues as $sKey => $sValue)
		{
            $this->_aAttributes[$sKey] = $sValue;
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

        $oTable   = static::table();
        $aColumns = $oTable->columns;

        // Keys will be attribute names; Values will be attributes values.
        $aFields = array();

        // Will contains LM to save in cascade.
        $aLinkedModels = array();

        // Avoid retrieving config option several times.
        $sDateTimeFormat = self::$_oConfig->databaseDateTimeFormat;

        foreach ($this->_aAttributes as $sKey => $mValue)
        {
            // Handles actual columns.
            if (isset($aColumns[$sKey]))
            {
                // Transform DateTime object into a database-formatted string.
                if ($mValue instanceof \DateTime)
                {
                    $mValue = $mValue->format($sDateTimeFormat);
                }

                $aFields[$sKey] = $mValue;
                continue;
            }

            // Handle linked models.
            // $mValue can be:
            // - a Model instance not saved yet (BelongsTo or HasOne relations);
            // - a Model instance ID (BelongsTo or HasOne relations) (Not
            // implemented).
            //
            // - a Model instance array (HasMany or ManyMany relations);
            // - a Model instance ID array (HasMany or ManyMany relations).
            if (isset(static::$_aRelations[$sKey]))
            {
                $oRelation = static::relation($sKey);

                // If it is linked by a BelongsTo instance, update local field.
                if ($oRelation instanceof BelongsTo)
                {
                    // Save in cascade.
                    $mValue->save();

                    // We use array_merge() and array_combine() in order to handle composed keys.
                    $aFields = array_merge($aFields, array_combine((array) $oRelation->cm->attribute, (array) $mValue->id));
                }
                // Otherwise, handle it later (After CM insert, actually).
                else
                {
                    $aLinkedModels[] = array('relation' => static::relation($sKey), 'object' => $mValue);
                }
            }
        }

        try
        {
            $oQuery = Query::update(array(
                'fields' => array_keys($aFields),
                'values' => array_values($aFields),
                'conditions' => array('id' => $this->_mId)
            ), get_called_class());
            $oQuery->run();

            // I know code seems (is) redundant, but I am convinced that it is
            // better this way. Treatment of linked model can be different
            // during insert than during update. See ManyMany treatment for
            // example: we delete before inserting.
            foreach ($aLinkedModels as $a)
            {
                // Avoid multiple array accesses.
                $oRelation = $a['relation'];
                $mObject   = $a['object'];

                // Ignore if not Model instance.
                if ($oRelation instanceof HasOne)
                {
                    if($mObject instanceof Model)
                    {
                        $mObject->{$oRelation->lm->attribute} = $this->_mId;
                        $mObject->save();
                    }
                }
                elseif ($oRelation instanceof HasMany)
                {
                    // Ignore if not Model instance.
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $mObject as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->{$oRelation->lm->attribute} = $this->_mId;
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
					$oQuery = Query::delete(array($oRelation->jm->from => $this->_mId), $oRelation->jm->table);
                    $oQuery->run();

                    $aValues = array();
                    // Array cast allows user not to bother to necessarily set an array.
                    foreach ((array) $mObject as $m)
                    {
                        if ($m instanceof Model)
                        {
                            $m->save();
                            $aValues[] = array($this->_mId, $m->_mId);
                        }
                        // Else we consider this is an ID.
                        else
                        {
                            $aValues[] = array($this->_mId, $m);
                        }
                    }

					$oQuery = Query::insert(array(
                        'fields' => array($oRelation->jm->from, $oRelation->jm->to),
                        'values' => $aValues
                    ), $oRelation->jm->table);

                    $oQuery->run();
                }
            }
        }
        catch (DatabaseException $oEx)
        {
            throw $oEx;
        }
        catch (Exception $oEx)
        {
            throw new Exception('Update failed for ' . get_class($this) . ' with ID: ' . $this->_mId .'.', 0, $oEx);
        }

        $this->_onAfterUpdate();

        return $this;
    }

}
