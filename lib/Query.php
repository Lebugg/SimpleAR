<?php
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */
namespace SimpleAR;

require 'queries/Condition.php';
require 'queries/Where.php';
require 'queries/Insert.php';
require 'queries/Select.php';
require 'queries/Count.php';
require 'queries/Update.php';
require 'queries/Delete.php';

/**
 * This class is the superclass of all SQL queries.
 *
 * It defines several static methods to build queries:
 *
 * * Query::insert();
 * * Query::select();
 * * Query::count();
 * * Query::update();
 * * Query::delete();
 */
abstract class Query
{
    /**
     * The database handler instance.
     *
     * @var \SimpleAR\Database
     */
    protected static $_oDb;

    /**
     * Is this query class critical?
     *
     * A critical query cannot be executed without a WHERE clause. Critical queries are:
     *
     * * UPDATE queries {@see SimpleAR\Query\Update}
     * * DELETE queries {@see SimpleAR\Query\Delete}
     *
     * @var bool Default false
     */
    protected static $_bIsCriticalQuery = false;

    /**
     * The query string.
     *
     * @var string
     */
	protected $_sSql;

    /**
     * The query values to bind.
     *
     * @var array.
     */
	protected $_aValues = array();

    protected $_aColumns = array();

    /**
     * Use Model properties to construct query?
     *
     * Some query classes can be construct by directly giving table name and table column names. But
     * using model offers more functionalities.
     *
     * @var bool Default true
     */
    /* protected $_bUseModel  = true; */

    /**
     * Use aliases in queries?
     *
     * @var bool Default true
     */
    //protected $_bUseAlias  = true;

    /**
     * Root model name.
     *
     * Only used when `$_bUseModel` is true.
     *
     * @var string
     */
	//protected $_sRootModel;

    /**
     * Table object of root model.
     *
     * Only used when `$_bUseModel` is true.
     *
     * @var Table
     */
	//protected $_oRootTable;

    /**
     * Root model table name.
     *
     * @var string
     */
	/* protected $_sRootTable = ''; */

    protected static $_aOptions = array();

    /**
     * Holds information used to properly construct queries. It allows
     * flexibility while keeping code clear.
     *
     * Description of its members:
     * ---------------------------
     *
     * - useAlias (bool): Tells if we use table aliases. If true, every table field use
     * in query will be prefix by the corresponding table alias.
     *
     * - useModel (bool): Indicate if we are using models to build query. If false, we
     * only use the raw table name given in constructor. In this case, we would
     * be enable to use many features like process query on linked models.
     *
     * - useResultAlias (bool)
     * - rootModel (string)
     * - rootTable (Table)
     * - rootTableName (string)
     * - isCriticalQuery (bool)
     *
     * @var object
     */
    protected $_oContext;

    /**
     * Constructor.
     *
     * @param string $sRoot The root model class name or the table name.
     * If $sRoot is a not a class name, `$_bUseModel` is set to `false` and $sRoot is considered as
     * a table name.
     */
	public function __construct($sRoot)
	{
        $this->_initContext($sRoot);
    }


    /**
     * Construct a Count query.
     *
     * @param array $aOptions The option array.
     * @param string $sRoot The root model class name or the table name.
     *
     * @return Query\Count
     */
	public static function count($aOptions, $sRoot)
	{
        return self::_query('Count', $aOptions, $sRoot);
	}

    /**
     * Construct a Delete query.
     *
     * @param array $aOptions The option array.
     * @param string $sRoot The root model class name or the table name.
     *
     * @return Query\Delete
     */
	public static function delete($aConditions, $sRoot)
	{
        // Delete query only needs a condition array, but we do not want to
        // redefine _build() for this.
        $aOptions = array('conditions' => $aConditions);

        return self::_query('Delete', $aOptions, $sRoot);
	}

    public static function init($oDatabase)
    {
        self::$_oDb = $oDatabase;
    }

    /**
     * Construct a Insert query.
     *
     * @param array $aOptions The option array.
     * @param string $sRoot The root model class name or the table name.
     *
     * @return Query\Insert
     */
	public static function insert($aOptions, $sRoot)
	{
        return self::_query('Insert', $aOptions, $sRoot);
	}

    /**
     * Returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement executed.
     *
     * @see http://php.net/manual/en/pdostatement.rowcount.php
     *
     * @return int
     */
    public function rowCount()
    {
        return $this->_oSth->rowCount();
    }

    /**
     * Execute the Query.
     *
     * @see http://www.php.net/manual/en/pdostatement.execute.php
     *
     * @return $this
     */
    public function run()
    {
        $this->_compile();

        if ($this->_oContext->isCriticalQuery)
        {
            if (strpos($this->_sSql, ' WHERE ') === false)
            {
                throw new Exception('Cannot execute this query without a WHERE clause.');
            }
        }

        $this->_oSth = self::$_oDb->query($this->_sSql, $this->_aValues);

        return $this;
    }

    /**
     * Construct a Select query.
     *
     * @param array $aOptions The option array.
     * @param string $sRoot The root model class name or the table name.
     *
     * @return Query\Select
     */
	public static function select($aOptions, $sRoot)
	{
        return self::_query('Select', $aOptions, $sRoot);
	}

    /**
     * Construct a Update query.
     *
     * @param array $aOptions The option array.
     * @param string $sRoot The root model class name or the table name.
     *
     * @return Query\Update
     */
	public static function update($aOptions, $sRoot)
	{
        return self::_query('Update', $aOptions, $sRoot);
	}

    /**
     * This function builds the query.
     *
     * @param array $aOptions The option array.
     *
     * @return void
     */
	protected function _build(array $aOptions)
    {
        foreach ($aOptions as $s => $a)
        {
            if (in_array($s, static::$_aOptions))
            {
                // There must be a method that corresponds to the option.
                $this->$s($a);
            }
        }
    }

    protected abstract function _compile();

    protected function _initContext($sRoot)
    {
        $this->_oContext = new \StdClass();

        // A Model class name is given.
		if (class_exists($sRoot))
		{
            if (! is_subclass_of($sRoot, '\SimpleAR\Model'))
            {
                throw new Exception('Given class "' . $sRoot . '" is not a subclass of Model.');
            }

            $this->_oContext->useModel = true;
            $this->_oContext->useAlias = true;

            $this->_oContext->rootModel       = $sRoot;
            $this->_oContext->rootTable       = $t = $sRoot::table();
            $this->_oContext->rootTableName   = $t->name;
		}

        // A table name is given.
		else
		{
            // We cannot use models if we only have a table name.
            $this->_oContext->useModel = false;
            $this->_oContext->useAlias = true;

            $this->_oContext->rootTableName   = $sRoot;
		}

        $this->_oContext->isCriticalQuery = self::$_bIsCriticalQuery;
	}

    private static function _query($sQueryClass, $aOptions, $sRoot)
    {
        $sQueryClass = "\SimpleAR\Query\\$sQueryClass";
        $oQuery      = new $sQueryClass($sRoot);

        $oQuery->_build($aOptions);

        return $oQuery;
    }

    /**
     * Apply aliases to columns.
     *
     * It may be necessary to add alias to columns:
     * - we want to prefix columns with table aliases;
     * - we want to rename columns in query result (only for Select queries).
     *
     * Note: The function name may be confusing.
     *
     * @param array $aColumns The column array. It can take two forms:
     * - an indexed array where values are column names;
     * - an associative array where keys are attribute names and values are
     * column names (for column renaming in result. Select queries only).
     *
     * @param string $sTableAlias  The table alias to prefix the column with.
     * @param string $sResultAlias The result alias to rename the column into.
     *
     * @return array
     */
    protected static function columnAliasing($aColumns, $sTableAlias = '', $sResultAlias = '')
    {
        $aRes = array();

        // If a table alias is given, add a dot to respect SQL syntax and to not
        // worry about it in following foreach loop.
        if ($sTableAlias)  { $sTableAlias  .= '.'; }


        // Result alias should only be used by Select queries.
        //
        // Should we do:
        //      if -> loop, else -> loop
        // or
        //      loop iteration -> if/else
        // ?
        if ($sResultAlias)
        {
            $sResultAlias .= '.';

            foreach ($aColumns as $sAttribute => $sColumn)
            {
                $aRes[] = $sTableAlias . $sColumn . ' AS `' . $sResultAlias . $sAttribute . '`';
            }
        }
        else
        {
            foreach ($aColumns as $sColumn)
            {
                $aRes[] = $sTableAlias . $sColumn;
            }
        }

        return $aRes;
    }

}
