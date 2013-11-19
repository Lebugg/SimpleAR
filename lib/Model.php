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

    protected static $_aDefaultValues = array();


    /**
     * The model instance's ID.
     *
     * This is the primary key of the model. It contains the value of DB field
     * name corresponding to $_mPrimaryKey.
     *
     * @var int
     */
    protected $_mId;

    protected $_aAttributes = array();

    protected $_bIsDirty = false;

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
    protected static $_aOrderBy = array();

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

        $this->_setDefaultValues();
        $this->_bIsDirty = true;
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
                call_user_func(array($this, 'set_' . $sName), $mValue);
            }
            else
            {
                $this->_aAttributes[$sName] = $mValue;
            }

            $this->_bIsDirty = true;
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

    /**
     * Very useful function to get an array of the attributes to select from
     * database. Attributes are values of $_aColumns.
     *
     *
     * @param string $mFilter Optional filter set for the
     * current model. It
     * prevents us to fetch all attribute from the
     * table.
     *
     * Note: it will NOT throw any exception
     * if filter does not exist; instead,
     * it will not use any filter.
     *
     * @return array
     */
    public static function columnsToSelect($sFilter = null, $sAlias = null)
    {
        $oTable = static::table();

        $aColumns    = $oTable->columns;
        $mPrimaryKey = $oTable->primaryKey;

        $aRes = ($sFilter !== null && isset(static::$_aFilters[$sFilter]))
            ? array_values(array_intersect_key($aColumns, array_flip(static::$_aFilters[$sFilter])))
            : array_values($aColumns)
            ;

        // Include primary key to columns to fetch. Useful only for simple
        // primary keys.
        if ($oTable->isSimplePrimaryKey)
        {
            $aRes[] = $mPrimaryKey;
        }

        if ($sAlias)
        {
            foreach ($aRes as &$sColumn)
            {
                $sColumn = $sAlias . '.' . $sColumn;
            }
        }

        return $aRes;
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
        return;
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

    public static function last($aOptions = array())
    {
        return self::find('last', $aOptions);
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
			if (!isset(static::$_aRelations[$sRelationName]))
			{
				throw new Exception('Relation "' . $sRelationName . '" does not exist for class "' . get_called_class() . '".');
			}

			// If relation is not yet initlialized, do it.
			if (!is_object(static::$_aRelations[$sRelationName]))
			{
				static::$_aRelations[$sRelationName] = Relationship::construct($sRelationName, static::$_aRelations[$sRelationName], get_called_class());
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
            $this->_bIsDirty = true;
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
		$this->_aAttributes = $aAttributes + $this->_aAttributes;
        $this->_bIsDirty    = true;

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

                    // @TODO: accept compound keys.
                    $aFields[] = $oRelation->cm->attribute;
                    $aValues[] = $mValue->id;
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
            $oQuery = Query::insert(array('fields' => $aFields, 'values' => $aValues), get_called_class());
            $oQuery->run();

            // We fetch the ID.
            $this->_mId = (int) self::$_oDb->lastInsertId();

            // Process linked models.
            foreach ($aLinkedModels as $a)
            {
                // Linked model instance is not yet saved. We want to save it on
                // cascade.
                // Ignore if not Model instance or already saved.
                if ($a['relation'] instanceof HasOne)
                {
                    if($a['object'] instanceof Model && $a['object']->_mId === null)
                    {
                        $a['object']->{$a['relation']->lm->attribute} = $this->_mId;
                        $a['object']->save();
                    }
                }
                elseif ($a['relation'] instanceof HasMany)
                {
                    // Linked model instance is not yet saved. We want to save it on
                    // cascade.
                    // Ignore if not Model instance or already saved.
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->{$a['relation']->lm->attribute} = $this->_mId;
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
                    foreach ($a['object'] as $m)
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
                        'fields' => array($a['relation']->jm->from, $a['relation']->jm->to),
                        'values' => $aValues
                    ), $a['relation']->jm->table);

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

            $aPrimaryKeyColumns = $oTable->columnRealName($mPrimaryKey);
            foreach ($aPrimaryKeyColumns as $sKey)
            {
                $this->_mId[] = $aRow[$sKey];
                //unset($aRow[$sKey]);
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

                    // @TODO: accept compound keys.
                    $aFields[] = $oRelation->cm->attribute;
                    $aValues[] = $mValue->id;
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
                'fields' => $aFields,
                'values' => $aValues,
                'conditions' => array('id' => $this->_mId)
            ), get_called_class());
            $oQuery->run();

            // I know code seems (is) redundant, but I am convinced that it is
            // better this way. Treatment of linked model can be different
            // during insert than during update. See ManyMany treatment for
            // example: we delete before inserting.
            foreach ($aLinkedModels as $a)
            {
                // Linked model instance is not yet saved. We want to save it on
                // cascade.
                // Ignore if not Model instance or already saved.
                if ($a['relation'] instanceof HasOne)
                {
                    if($a['object'] instanceof Model && $a['object']->_mId === null)
                    {
                        $a['object']->{$a['relation']->lm->attribute} = $this->_mId;
                        $a['object']->save();
                    }
                }
                elseif ($a['relation'] instanceof HasMany)
                {
                    // Linked model instance is not yet saved. We want to save it on
                    // cascade.
                    // Ignore if not Model instance or already saved.
                    foreach ($a['object'] as $o)
                    {
                        if ($o instanceof Model && $o->id === null)
                        {
                            $o->{$a['relation']->lm->attribute} = $this->_mId;
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
					$oQuery = Query::delete(array($a['relation']->jm->from => $this->_mId), $a['relation']->jm->table);
                    $oQuery->run();

                    $aValues = array();
                    foreach ($a['object'] as $m)
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
                        'fields' => array($a['relation']->jm->from, $a['relation']->jm->to),
                        'values' => $aValues
                    ), $a['relation']->jm->table);

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
