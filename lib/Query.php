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
    protected $_bUseModel  = true;

    /**
     * Use aliases in queries?
     *
     * @var bool Default true
     */
    protected $_bUseAlias  = true;

    /**
     * Root model name.
     *
     * Only used when `$_bUseModel` is true.
     *
     * @var string
     */
	protected $_sRootModel;

    /**
     * Table object of root model.
     *
     * Only used when `$_bUseModel` is true.
     *
     * @var Table
     */
	protected $_oRootTable;

    /**
     * Root model table name.
     *
     * @var string
     */
	protected $_sRootTable = '';

    protected static $_aOptions = array();

    /**
     * Constructor.
     *
     * @param string $sRoot The root model class name or the table name.
     * If $sRoot is a not a class name, `$_bUseModel` is set to `false` and $sRoot is considered as
     * a table name.
     */
	public function __construct($sRoot)
	{
		if (class_exists($sRoot))
		{
            $this->_sRootModel = $sRoot;
            $this->_oRootTable = $sRoot::table();
			$this->_sRootTable = $this->_oRootTable->name;
		}
		else
		{
			$this->_bUseModel  = false;
            $this->_bUseAlias  = false;
			$this->_sRootTable = $sRoot;
		}
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

        if (static::$_bIsCriticalQuery)
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

    private static function _query($sQueryClass, $aOptions, $sRoot)
    {
        $sQueryClass = "\SimpleAR\Query\\$sQueryClass";
        $oQuery      = new $sQueryClass($sRoot);

        $oQuery->_build($aOptions);

        return $oQuery;
    }

}
