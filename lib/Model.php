<?php
namespace SimpleAR;

/**
 * This file contains the Model class.
 *
 * @author Damien Launay
 */

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
     * @var Database
     */
    protected static $_oDb;

    /**
     * Contains configuration.
     *
     * @var Config
     */      
    protected static $_oConfig;

    /**
     * The database table name of the model.
     *
     * @var string
     */
    protected static $_sTableName;

    /**
     * The primary key name of the model.
     *
     * It MUST correspond to a valid primary key in the model table. Or, at
     * least, the field should have these properties:
     *  * unique ;
     *  * integer ;
     *  * auto-increment.
     *
     * @var string
     */
    protected static $_mPrimaryKey;

    /**
     * This array contains associations between class members and
     * database fields.
     *
     * Advantages of this array are multiple:
     *  * DB field names abstraction (some of them are meaningless) ;
     *  * Defines which fields will be retrieved (some of them are useless) ;
     *
     * An inconvenient is that it is necessary to declare a class member
     * corresponding to $_aColumns keys.
     *
     * The array is constructed this way:
     * <code>
     *  array(
     *      'myAttribute' => 'db_field_name_for_my_attribute',
     *      ...
     *  );
     * </code>
     *
     * And so, we must define a <b>public</b> class member:
     * <code>
     *  public $myAttribute;
     *  ...
     * </code>
     *
     * It is possible to specify a default value for each member. These value
     * will be used for model instance insertion in DB.
     * Example:
     * <code>
     *  public $myAttribute = 12;
     *  ...
     * </code>
     * If nothing is set for this member before saving, value 12 will be
     * inserted.
     *
     * @var array
     */
    protected static $_aColumns = array();

    protected static $_aTranslations = array();

    protected static $_aDefaultValues = array();

    protected $_aAttributes = array();

    /**
     * The model instance's ID.
     *
     * This is the primary key of the model. It contains the value of DB field
     * name corresponding to $_mPrimaryKey.
     *
     * @var int
     */
    protected $_mId;

    /**
     * This array contains the list of filters defined for the model.
     *
     * Filters help you to manage data you want to render. It is useful to
     * choose only relevent data according to the user, the option specified in
     * the URL.
     *
     * Here is the syntax of this array:
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
     *
     * <filter's name> is an arbitrary name you choose for the filter. field1,
     * field2 are model's attributes (that is to say, key of
     * static::$_aColumns).
     *
     * Note: A special filter named "search" can be declared; if so, it will be
     * used for a search request.
     *
     * Note: Use filtering BEFORE loading model instance; this way, you will
     * prevent database to retrieve all fields from the model table.
     */
    protected static $_aFilters = array();

    /**
     * Current filter name.
     *
     * This attribute contains the current filter name. It has to be an entry of
     * ModelAbstract::$_aFilters.
     *
     * @see ModelAbstract::_loadAccordingToFilter()
     * @see ModelAbstract::_aFilters
     *
     * @var string
     */
    protected $_sCurrentFilter = null;

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
     * <code>
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
     * </code>
     *
     * In the above example, it will check (before insert) that couple
     * ('myAttribute1', 'myAttribute2') will still satisfy unicity constraint
     * after model insertion. Then, unicity on 'myAttribute3' will be checked.
     *
     * If any of the constraints is not respected, an Exception is thrown.
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
    protected static $_aOrder = array();

    /**
     * This array allows the creation of link (or relations) with
     * other models.
     *
     * It is constructed this way:
     * <code>
     *  array(
     *      "fieldName" => array(
     *          "type" => <relation type>,
     *          "model" => <model name>,
     *          "load_mode" => <string> ('always' | 'explicit'),
     *          "on_delete_cascade" => <bool>,
     *          <And other fields depending on relation type>
     *      ),
     *  ...
     *  );
     * </code>
     *
     * In this section, "CM" stands for Current Model; "LM" stands for Linked
     * Model.
     *
     * Explanation of different fields:
     *  * type: The relation type with the model. Can be "belongs_to", "has_one",
     *  "has_many" or "many_many" for the moment;
     *  * model: The model class name without the "Model" suffix. For example:
     *  "model" => "Company" for CompanyModel;
     *  * load_mode: Relation will be needed according to load mode.
     *  * on_delete_cascade: If true, delete linked model(s).
     *
     * But there are also fields that depend on relation type. Let's see them:
     *
     *  * belongs_to:
     *      * key_from: CM attribute that links to LM. Mandatory.
     *
     *  * has_one:
     *      * key_from: CM field that links to LM.
     *                  Optional. Default: CM::_mPrimaryKey.
     *                  It is possible to change the value to a CM::_aColumns key if
     *                  the link is not made on CM primary key.
     *
     *      * key_to:   LM field that links to CM.
     *                  Optional. Default: UserModel => userId
     *                  It is possible to change the value to a LM::_aColumns key if
     *                  the link is not made in CM primary key or if the CM PK name is
     *                  not the same that the LM foreign key name.
     *  * has_many:
     *      * key_from: CM unique field that links to key_to.
     *                  Optional. Default: UserModel => userId
     *                  Can be change if the link is not made on CM primary key.
     *
     *      * key_to:   LM field that links to key_from.
     *                  Optional. Default: CM::_mPrimaryKey (same name).
     *
     *  * many_many:
     *      * join_table: The table that makes the join between the two models.
     *
     *      * key_from: CM unique field that links to join_from. Default:
     *      CM::_mPrimaryKey.
     *
     *      * join_from: Join table field that links to CM primary key.
     *                  Optional. Default: CM::_mPrimaryKey.
     *
     *      * join_to:   Join table field that links to LM primary key.
     *                  Optional. Default: LM::_mPrimaryKey.
     *
     *      * key_to : LM unique field that links to join_to. Default:
     *      LM::_mPrimaryKey.
     *
     * @var array
     */
    protected static $_aRelations = array();

    private static $_aTables = array();

    /**
     * Constructor.
     *
     * @param int $iId The ID of the instance we want. If null, no instance will
     * be loaded.
     *
     * @param array $aOptions. An array option that can be specified. This array 
     * can contains following entries:
     *  - "filter": An optional filter to apply to the model.
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
    }

    /**
     * If users try to access to an unknown property, checks if it is defined in
     * model relations array. If yes, loads it.
     *
     * If $sName is equal to "id", it will return the ID of the model instance.
     *
     * @param string $s The property name.
     * @return mixed
     */
    public function __get($s)
    {
		$bCount = ($s[0] === '#');
		if ($bCount)
		{
			$s = substr($s, 1);
		}

        if ($s == 'id')
        {
            return $this->_mId;
        }

        if (isset($this->_aAttributes[$s]) || array_key_exists($s, $this->_aAttributes))
        { 
            if (method_exists($this, 'get_' . $s))
            {
                return call_user_method('get_' . $s, $this);
            }
         
            return $bCount ? count($this->_aAttributes[$s]) : $this->_aAttributes[$s];
        }

        // Will arrive here maximum once per relation because when a relation is
        // loaded, an attribute named as the relation is appended to $this->_aAttributes.
        // So, it would return it right above.
        if (isset(static::$_aRelations[$s]))
        {
			if ($bCount)
			{
				return $this->_countLinkedModel($s);
			}

            $this->_aAttributes[$s] = $this->_loadLinkedModel($s);

            if (method_exists($this, 'get_' . $s))
            {
                return call_user_method('get_' . $s, $this);
            }

            return $this->_aAttributes[$s];
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

    public function __set($sName, $mValue)
    {
        $aColumns = static::table()->columns;

        if (isset($aColumns[$sName])
            || isset(static::$_aRelations[$sName])
			|| isset($this->_aAttributes[$sName]) 
			|| array_key_exists($sName, $this->_aAttributes)
		) {
            if (method_exists($this, 'set_' . $sName))
            {
                call_user_method('set_' . $sName, $this, $mValue);
            }
            else
            {
                $this->_aAttributes[$sName] = $mValue;
            }

			return;
        }

        $aTrace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $sName .
            ' in ' . $aTrace[0]['file'] .
            ' on line ' . $aTrace[0]['line'],
            E_USER_NOTICE
		);
    }

    public static function all($aOptions = array())
    {
        return self::find('all', $aOptions);
    }

    /**
     * Return all PUBLIC attributes of the instance with their values.
     *
     * @return array The instance attributes with their values.
     */
    public function attributes()
    {
        return array('id' => $this->_mId) + $this->_aAttributes;
    }

    public static function count($aOptions = array())
    {
        return self::find('count', $aOptions);
    }

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

        if ($iCount == 0)
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
        if (!is_array($mKey))
        {
            static::$_aExcludedKeys[] = $mKey;
			unset($this->_aAttributes[$mKey]);
        }
        else
        {
			foreach ($mKey as $sKey)
			{
				static::$_aExcludedKeys[] = $sKey;
				unset($this->_aAttributes[$sKey]);
			}
        }

        return $this;
    }

    /**
     * Tests if a model instance represented by its ID exists.
     *
     * @param int $iId The ID to test on.
     *
     * @return bool True if it exists, false otherwise.
     */
    public static function exists($m)
    {
        // It is an associative array. We want to test record existence by an
        // array of condtitions.
        if (is_array($m) && !isset($m[0]))
        {
            return (bool) static::find('first', array('conditions' => $m));
        }

        // Classic exists() (By primary key.)
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

    /**
     * Very useful function to get an array of the attributes to select from
     * database. Attributes are values of $_aColumns.
     *
     *
     * @param string $mFilter Optional filter set for the current model. It
     * prevents us to fetch all attribute from the table.
     *
     * Note: it will NOT throw any exception if filter does not exist; instead,
     * it will not use any filter.
     *
     * @return array
     */
    public static function columnsToSelect($sFilter = null, $sAlias = null)
    {
        $oTable = static::table();

        $aColumns    = $oTable->columns;
        $mPrimaryKey = $oTable->primaryKey;

        $aRes = ($sFilter && isset(static::$_aFilters[$sFilter]))
            ? $aRes = array_values(array_intersect_key($aColumns, array_flip(static::$_aFilters[$sFilter])))
            : $aRes = array_values($aColumns)
            ;

        // Include primary key to  columns to fetch.
        if ($oTable->isSimplePrimaryKey)
        {
            $aRes[] = $mPrimaryKey;
        }
        else
        {
            $aRes = array_merge($aRes, $mPrimaryKey);
        }

		if ($sAlias)
		{
			$iCount = count($aRes);
			for ($i = 0 ; $i < $iCount ; ++$i)
			{
				$aRes[$i] = $sAlias . '.' . $aRes[$i];
			}
		}

        return $aRes;
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

    public static function find($mFirst, $aOptions = array())
    {
        // Find by primary key. It can be an array when using compound primary 
        // keys.
        if (ctype_digit($mFirst) || is_array($mFirst))
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

        return self::_processSqlQuery($oQuery->sql, $oQuery->values, $sMultiplicity, $aOptions);
    }

    public static function findByPK($mId, $aOptions = array())
    {
        // Handles multiple primary keys, too.
        $aOptions['conditions'] = array('id' => $mId);

        // Fetch model.
		$oQuery = Query::select($aOptions, get_called_class());
        if (!$oModel = self::_processSqlQuery($oQuery->sql, $oQuery->values, 'one', $aOptions))
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

    public static function init()
    {
        if (!self::$_oConfig) { self::$_oConfig = Config::instance();   }
		if (!self::$_oDb)     { self::$_oDb     = Database::instance(); }

		// get_called_class() returns the namespaced class. So we have to parse
		// it.
		$a = explode('\\', get_called_class());
		$sCurrentClass = array_pop($a);

		// Set defaults for model classes
		if ($sCurrentClass != 'Model')
		{
            $sTableName  = static::$_sTableName  ?: call_user_func(self::$_oConfig->classToTable, $sCurrentClass);
            $mPrimaryKey = static::$_mPrimaryKey ?: self::$_oConfig->primaryKey;

			// Fetch columns if required.
			if (self::$_oConfig->autoRetrieveModelColumns)
			{
                $aColumns          = array();
				$aDbColumns        = self::$_oDb->query('SHOW COLUMNS FROM ' . $sTableName)->fetchAll(\PDO::FETCH_COLUMN);
                $bSimplePrimaryKey = is_string($mPrimaryKey);

                $iCount = count($aColumns);
                foreach ($aDbColumns as $s)
                {
                    // We do not want to insert primary key in static::$_aColumns.
                    if ( ($bSimplePrimaryKey && $s !== $mPrimaryKey)
                        || (!$bSimplePrimaryKey && !in_array($s, $mPrimaryKey))
                    ) {
						// If a column is renamed, take the new name.
						$sKey = isset(static::$_aTranslations[$s]) ? static::$_aTranslations[$s] : $s;
                        $aColumns[$sKey] = $s;
                    }
				}
			}
            else
            {
                $aColumns = static::$_aColumns;
            }

            self::$_aTables[$sCurrentClass] = new Table($sTableName, $mPrimaryKey, $aColumns, static::$_aOrder);
		}
    }

    public static function last($aOptions = array())
    {
        return self::find('last', $aOptions);
    }

    /**
     * Getter. Returns the model primary key name.
     *
     * @return string The model primary key name.
     */
    public static function primaryKey()
    {
        return static::table()->primaryKey();
    }

    /**
     * Saves the instance to the database. Will insert it if not yet created or
     * update otherwise.
     *
     * @return bool True if saving is OK, false otherwise.
     */
    public function save()
    {
        return $this->_mId === NULL ? $this->_insert() : $this->_update();
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

    /**
     * Getter. Returns the model table name.
     *
     * @return string The model table name.
     */
    public static function tableName()
    {
        return static::table()->name();
    }

    public function toArray()
    {
        $aRes = $this->attributes();

        return self::_arrayToArray($aRes);
    }

    private static function _arrayToArray($aArray)
    {
        // It is faster to iterate this way instead using a foreach when
        // altering the array.
        $aHash  = array_keys($aArray);
        $iCount = count($aArray);

        for ($i = 0 ; $i < $iCount ; ++$i)
        {
            if (is_object($aArray[$aHash[$i]]))
            {
                $aArray[$aHash[$i]] = $aArray[$aHash[$i]]->toArray();
                continue;
            }

            if (is_array($aArray[$aHash[$i]]))
            {
                $aArray[$aHash[$i]] = self::_arrayToArray($aArray[$aHash[$i]]);
                continue;
            }
        }

        return $aArray;
    }

    protected function _attr($sAttributeName)
    {
        if (func_num_args() === 1)
        {
            return $this->_aAttributes[$sAttributeName];
        }
        else
        {
            $this->_aAttributes[$sAttributeName] = func_get_arg(1);
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

    protected static function _conditionsToSql($aConditions, $mUseAlias = true)
    {
        $oTable = static::table();

        $aTablesIn     = array();
        $aAndClauses   = array();
        $aValuesToBind = array();
        $sFrom         = '';

        $sRootClass = get_called_class();

		if ($mUseAlias !== false)
		{
			$sRootAlias = $mUseAlias === true ? $oTable->alias : $mUseAlias;
			$mUseAlias  = true;
		}

        foreach ($aConditions as $mConditionKey => $mConditionValue)
        {
            // We accept two forms of conditions:
            // 1) Basic conditions:
            //      array(
            //          'my/attribute' => 'myValue',
            //          ...
            //      )
            // 2) Conditions with operator:
            //      array(
            //          array('my/attribute', 'myOperator', 'myValue'),
            //          ...
            //      )
            //
            // Operator: =, !=, IN, NOT IN, >, <, <=, >=.

            if (is_string($mConditionKey)) // We are in case 1).
            {
                $sAttribute = $mConditionKey;
                $mValue     = $mConditionValue;
                $sOperator  = null;
            }
            else // We are in case 2).
            {
                $sAttribute  = $mConditionValue[0];
                $mValue      = $mConditionValue[2];
                $sOperator   = $mConditionValue[1];
            }

            $aPieces = explode('/', $sAttribute);
            $iCount  = count($aPieces);

            // Empty string or other errors.
            if (!$iCount)
            {
                throw new Exception('Invalid condition attribute: "' . $sAttribute . '".');
            }

            // The last item of $aPieces is the attribute the condition is 
            // made on.
            $mAttribute = explode(',', array_pop($aPieces));

            // We check if given attribute is a compound attribute (a couple, for example).
            if (count($mAttribute) == 1) // Simple attribute.
            {
                $mAttribute = $mAttribute[0];
            }

            if (is_string($mAttribute)) // Simple attribute.
            {
                // The condition is simply made on a model's attribute.
                if ($iCount == 1 && $oTable->hasColumn($mAttribute))
                {
                    // Construct right hand part of the condition.
                    if (is_array($mValue))
                    {
                        $sConditionValueString = '(' . str_repeat('?,', count($aCondition[1]) - 1) . '?)';
                        $sOperator = $sOperator ?: 'IN';
                    }
                    else
                    {
                        $sConditionValueString = '?';
                        $sOperator = $sOperator ?: '=';
                    }

                    $aAndClauses[] = $mUseAlias
								   ? $sRootAlias . '.' . $oTable->columnRealName($mAttribute) . ' ' . $sOperator . ' ' . $sConditionValueString
								   : $oTable->columnRealName($mAttribute) . ' ' . $sOperator . ' ' . $sConditionValueString
								   ;
                    $aValuesToBind = array_merge($aValuesToBind, is_array($mValue) ? $mValue : array($mValue));

                    // Jump to next condition.
                    continue;
                }
            }
            else // Compound attribute;
            {
                $aTmp      = array();
                $aTmp2     = array();
                $sOperator = $sOperator ?: '=';

                foreach ($mValue as $aTuple)
                {
                    foreach ($mAttribute as $i => $sAttribute)
                    {
                        $sColumn = $oTable->columnRealName($sAttribute);

                        $aTmp[] = $mUseAlias ? "$sRootAlias.{$sColumn} {$sOperator} ?" : "{$sColumn} {$sOperator} ?";
                        $aValuesToBind[] = $aTuple[$i];
                    }

                    $aTmp2[] = '(' . implode(' AND ', $aTmp) . ')';
                    $aTmp    = array();
                }

                // The 'OR' simulates a IN statement for compound keys.
                $aAndClauses[] = '(' . implode(' OR ', $aTmp2) . ')';
            }

            // The condition is more complex.

            $sCurrentModelClass = $sRootClass;

            // Data container about final attribute and comparison type. Used by 
            // Relationship class.
            $o = new \StdClass();
            $o->operator   = $sOperator ?: '=';
            $o->logic      = 'or'; // If we want a AND logic, we have to define a syntax first.
            $o->value      = $mValue;
            $o->valueCount = is_array($mValue) ? count($mValue) : 1;
            $o->attribute  = $mAttribute;

            // We do specific treatments for the last relation.
            $sLastRelationName = array_pop($aPieces);

            // From here, every $sPiece must be a relation name of 
            // $sCurrentModelClass. It would throw an exception otherwise.
            foreach ($aPieces as $sPiece)
            {
                $oRelation          = $sCurrentModelClass::relation($sPiece);
                $sFrom             .= $oRelation->joinLinkedModel($aTablesIn);

                // Go forward through relations arborescence.
                $sCurrentModelClass = $oRelation->linkedModelClass();
            }

            // We now process the last relation.
            $oRelation     = $sCurrentModelClass::relation($sLastRelationName);
            $sFrom        .= $oRelation->joinAsLast($aTablesIn, $o); // <-- Here is $o var!
            $aAndClauses[] = $oRelation->condition($o);
            $aValuesToBind = array_merge($aValuesToBind, (array) $o->value); // @todo: flatten value array.
        }

        return array('from' => $sFrom, 'ands' => implode(' AND ', $aAndClauses), 'values' => $aValuesToBind);
    }

    /**
     * Construct a SQL ORDER BY clause according to static::$_aOrder array.
     *
     * @param string $sAlias Table alias used in the SQL query (mandatory).
	 *
	 * Does not handle multiple primary keys.
     *
     * @return string The ORDER BY clause.
     */
    private static function _constructSqlOrderClause($aOrder, $sAlias)
    {
        $aRes   = array();
        $oTable = static::table();

        // If there are common keys between static::$_aOrder and $aOrder, 
        // entries of static::$_aOrder will be overwritten.
        foreach (array_merge(static::$_aOrder, $aOrder) as $sField => $sOrder)
        {
            $aRes[] = $sAlias.'.'. $oTable->columnRealName($sField) . ' ' . $sOrder;
        }

        return $aRes ? ' ORDER BY ' . implode(',', $aRes) : '';
    }

    private function _countLinkedModel($sRelationName)
    {
        $oRelation = static::relation($sRelationName);

        // Current object is not saved in database yet. It does not
        // have an ID, so we cannot retrieve linked models from Db.
        if ($this->id === null)
        {
			return 0;
        }

        // Our object is already saved. It has an ID. We are going to
        // fetch potential linked objects from DB.
		$iRes     = 0;
		$sLMClass = $oRelation->linkedModelClass();

        if ($oRelation instanceof BelongsTo)
        {
            return $this->{$oRelation->keyFrom()} === NULL ? 0 : 1;
        }
        elseif ($oRelation instanceof HasOne || $oRelation instanceof HasMany)
        {
            $iRes = $sLMClass::count(array(
                'conditions' => array_merge($oRelation->conditions(), array($oRelation->keyTo() => $this->{$oRelation->keyFrom()})),
            ));
        }
        else // ManyMany
        {
            $sQuery = 'SELECT COUNT(*) FROM ' . $oRelation->linkedModelTable() . ' lt'
                    . ' JOIN ' . $oRelation->joinTable() . ' jt ON jt.' . $oRelation->joinKeyTo() . ' = lt.' . $oRelation->keyTo(true)
                    . ' WHERE jt.' . $oRelation->joinKeyFrom() . ' = ?';

			$aValues = is_string($this->_mId) ? array($this->_mId) : $this->_mId;
			
			// Use relation's conditions.
			$aSqlConditions = $sLMClass::_conditionsToSql($oRelation->conditions(), 'lt');
			if ($aSqlConditions['ands'])
			{
				$sQuery .= ' AND ' . $aSqlConditions['ands'];
				$aValues = array_merge($aValues, $aSqlConditions['values']);
			}

			// Use relation's ORDER BY clause.
			$sQuery .= $sLMClass::_constructSqlOrderClause($oRelation->order(), 'lt');

            $iRes = $sLMClass::_processSqlQuery($sQuery, $aValues, 'count', array('filter' => $oRelation->filter()));
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

			if ($oRelation->onDeleteCascade())
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
		$this->_aAttributes = $aAttributes;

		/* Restrictive way of doing it:
        $aColumns = static::table()->columns();

        foreach ($aAttributes as $sKey => $sValue)
        {
            if (isset($aColumns[$sKey]) || isset(static::$_aRelations[$sKey]))
            {
                $this->_aAttributes[$sKey] = $sValue;
            }
        }
		*/
    }

    /**
     * Inserts the object in database.
     *
     * @return bool True on success, false otherwise.
     * @throws \PDOException if a database error occurs.
     */
    private function _insert()
    {
        $this->_onBeforeInsert();
        $this->_checkUniqueConstraints();

        $oTable = static::table();
        $sTableName  = $oTable->name;
        $aColumns    = $oTable->columns;

        $aFields = array();
        $aValues = array();

        $aLinkedModels = array();

        foreach ($this->_aAttributes as $sKey => $mValue)
        {
            // Handle actual columns.
            if (isset($aColumns[$sKey]))
            {
                $aFields[] = $sKey;
                $aValues[] = $mValue;
                continue;
            }

            // Handle linked models.
            if (isset(static::$_aRelations[$sKey]))
            {
                $oRelation = static::relation($sKey);

                if ($oRelation instanceof BelongsTo)
                {
                    if ($mValue->id === null)
                    {
                        $mValue->save();
                    }

                    $mKeyFrom = $oRelation->keyFrom();
                    if (is_string($mKeyFrom))
                    {
                        $aFields[] = $mKeyFrom;
                        $aValues[] = $mValue->id;
                    }
                    else
                    {
                        foreach ($mKeyFrom as $sKey)
                        {
                            $aFields[] = $sKey;
                        }

                        $aValues = array_merge($aValues, $mValue->id);
                    }
                }
                else
                {
                    $aLinkedModels[] = array('relation' => static::relation($sKey), 'object' => $mValue);
                }
            }
        }


        try
        {
            $oQuery = Query::insert(array('fields' => $aFields, 'values' => $aValues), get_called_class());
            $oQuery->run();

            // We fetch the ID.
            $this->_mId = (int) self::$_oDb->lastInsertId();

            // Process linked models.
            foreach ($aLinkedModels as $a)
            {
                if ($a['relation'] instanceof HasOne)
                {
                    if ($a['object'] instanceof Model && $a['object']->id === null)
                    {
                        $a['object']->{$a['relation']->keyTo()} = $this->_mId; // Does not handle compound keys.
                        $a['object']->save();
                    }
                }
                elseif ($a['relation'] instanceof HasMany)
                {
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->{$a['relation']->keyTo()} = $this->_mId; // Does not handle compound keys.
                            $o->save();
                        }
                    }
                }
                else // ManyMany
                {
                    $aValues = array();
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->save();
                            $aValues[] = array($this->id, $o->id);
                        }
                        elseif (is_int($o))
                        {
                            $aValues[] = array($this->id, $o);
                        }
                    }

					$oQuery = Query::insert(array(
                        'fields' => array($a['relation']->joinKeyFrom(), $a['relation']->joinKeyTo()),
                        'values' => $aValues
                    ), $a['relation']->joinTable());

                    $oQuery->run();
                }
            }
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
     * \PDOStatement:fetch() function.
     *
     * @return $this
     */
    private function _load($aRow)
    {
        $this->_onBeforeLoad();

        $oTable      = static::table();
        $mPrimaryKey = $oTable->primaryKey;

        // We set our object ID.
        if ($oTable->isSimplePrimaryKey)
        {
            $this->_mId = $aRow[$mPrimaryKey];
            unset($aRow[$mPrimaryKey]);
        }
        else
        {
            $this->_mId = array();

            foreach ($mPrimaryKey as $sKey)
            {
                $this->_mId[] = $aRow[$sKey];
                unset($aRow[$sKey]);
            }
        }

        // We fill the class members.
        $aMap = array_flip($oTable->columns);
        foreach ($aRow as $sKey => $sValue)
		{
            // $aMap[$sKey] gives us the class member name associated to the
            // DB field. So we set the member with the right value.
            // The condition prevents the error of a missing class member;
            // remove it for stronger consistency.
            if (isset($aMap[$sKey]))
			{
                $this->_aAttributes[$aMap[$sKey]] = $sValue;
            }
        }

        $this->_setDefaultValues();
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
        if ($this->id === null)
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
		$sLMClass = $oRelation->linkedModelClass();

        if ($oRelation instanceof BelongsTo)
        {
            try
            {
                $mRes = $sLMClass::findByPK($this->{$oRelation->keyFrom()}, array(
					'conditions' => $oRelation->conditions(),
					'filter'	 => $oRelation->filter(),
				));
            }
            // Prevent exception bubbling.
            catch (RecordNotFoundException $oEx) {}
        }
        elseif ($oRelation instanceof HasOne)
        {
            $mRes = $sLMClass::first(array(
                'conditions' => array_merge($oRelation->conditions(), array($oRelation->keyTo() => $this->{$oRelation->keyFrom()})),
				'order'		 => $oRelation->order(),
                'filter'     => $oRelation->filter(),
            ));
        }
        elseif ($oRelation instanceof HasMany)
        {
            $mRes = $sLMClass::all(array(
                'conditions' => array_merge($oRelation->conditions(), array($oRelation->keyTo() => $this->{$oRelation->keyFrom()})),
				'order'		 => $oRelation->order(),
                'filter'     => $oRelation->filter(),
            ));
        }
        else // ManyMany
        {
            $sQuery = 'SELECT lt.' . implode(',lt.', $sLMClass::columnsToSelect($oRelation->filter())) . ' FROM ' . $oRelation->linkedModelTable() . ' lt'
                    . ' JOIN ' . $oRelation->joinTable() . ' jt ON jt.' . $oRelation->joinKeyTo() . ' = lt.' . $oRelation->keyTo(true)
                    . ' WHERE jt.' . $oRelation->joinKeyFrom() . ' = ?';

			$aValues = is_string($this->_mId) ? array($this->_mId) : $this->_mId;
			
			// Use relation's conditions.
			$aSqlConditions = $sLMClass::_conditionsToSql($oRelation->conditions(), 'lt');
			if ($aSqlConditions['ands'])
			{
				$sQuery .= ' AND ' . $aSqlConditions['ands'];
				$aValues = array_merge($aValues, $aSqlConditions['values']);
			}

			// Use relation's ORDER BY clause.
			$sQuery .= $sLMClass::_constructSqlOrderClause($oRelation->order(), 'lt');

            $mRes = $sLMClass::_processSqlQuery($sQuery, $aValues, 'several', array('filter' => $oRelation->filter()));
        }

        return $mRes;
    }


    private static function _processSqlQuery($sQuery, $aParams, $sMultiplicity, $aOptions)
    {
        $oSth   = self::$_oDb->query($sQuery, $aParams);
        $mRes   = null;

        switch ($sMultiplicity)
		{
            case 'count':
                $mRes = $oSth->fetchColumn();
                break;

            case 'one':
                $aRow = $oSth->fetch(\PDO::FETCH_ASSOC);
                if ($aRow)
				{
                    $mRes = new static(null, $aOptions);
                    $mRes->_load($aRow);
                }
                break;

            case 'several':
                $aRows = $oSth->fetchAll(\PDO::FETCH_ASSOC);
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

    /**
     * Get the relation identified by its name.
     *
     * @param $sRelationName The name of the relation.
     * @return object A Relationship object.
     */
    public static function relation($sRelationName)
    {
        $a = static::$_aRelations;

        if (!isset($a[$sRelationName]))
        {
            throw new Exception('Relation "' . $sRelationName . '" does not exist for class "' . get_called_class() . '".');
        }

        // If relation is not yet initlialized, do it.
        if (!is_object($a[$sRelationName]))
        {
            $a[$sRelationName] = Relationship::construct($sRelationName, $a[$sRelationName], get_called_class());
        }

        // Return the Relationship object.
        return $a[$sRelationName];
    }

    public static function remove($aConditions = array())
    {
        $oQuery = Query::delete($aConditions, get_called_class());
        return $oQuery->run()->rowCount();
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

        $aFields = array();
        $aValues = array();

        $aLinkedModels = array();

        foreach ($this->_aAttributes as $sKey => $mValue)
        {
            // Handles actual columns.
            if (isset($aColumns[$sKey]))
            {
                $aFields[] = $sKey;
                $aValues[] = $mValue;
                continue;
            }

            if (isset(static::$_aRelations[$sKey]))
            {
                $oRelation = static::relation($sKey);

                if ($oRelation instanceof BelongsTo)
                {
                    if ($mValue->id === null)
                    {
                        $mValue->save();
                    }

                    $mKeyFrom = $oRelation->keyFrom(true);
                    if (is_string($mKeyFrom))
                    {
                        $aFields[] = $oRelation->keyFrom();
                        $aValues[] = $mValue->id;
                    }
                    else
                    {
                        foreach ($mKeyFrom as $sKey)
                        {
                            $aFields[] = $sKey;
                        }

                        $aValues = array_merge($aValues, $mValue->id);
                    }
                }
                else
                {
                    $aLinkedModels[] = array('relation' => static::relation($sKey), 'object' => $mValue);
                }
            }
        }

        try
        {
            $oQuery = Query::update(array(
                'fields' => $aFields,
                'values' => $aValues,
                'conditions' => array('id' => $this->_mId)
            ), get_called_class());
            $oQuery->run();

            // Process linked models.
            foreach ($aLinkedModels as $a)
			{
                if ($a['relation'] instanceof HasOne)
                {
                    if ($a['object'] instanceof Model && $a['object']->id === null)
                    {
                        $a['object']->{$a['relation']->keyTo()} = $this->_mId; // Does not handle compound keys.
                        $a['object']->save();
                    }
                }
                elseif ($a['relation'] instanceof HasMany)
                {
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->{$a['relation']->keyTo()} = $this->_mId; // Does not handle compound keys.
                            $o->save();
                        }
                    }
                }
                else // ManyMany
                {
                    // Remove all rows from join table. (Easier this way.)
					$oQuery = Query::delete(array($a['relation']->joinKeyFrom() => $this->id), $a['relation']->joinTable());
                    return $oQuery->run();

                    $aValues = array();
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->save();
                        }

                        // Does not handle compound keys.
                        $aValues[] = array($this->id, $o->id);
                    }

					$oQuery = Query::insert(array(
                        'fields' => array($a['relation']->joinKeyFrom(), $a['relation']->joinKeyTo()),
                        'values' => $aValues
                    ), $a['relation']->joinTable());
                    $oQuery->run();
                }
            }

        }
        catch (Exception $oEx)
        {
            throw new Exception('Update failed for ' . get_class($this) . ' with ID: ' . $this->_mId .'.', 0, $oEx);
        }

        $this->_onAfterUpdate();

        return $this;
    }

}
