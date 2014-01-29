<?php
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */
namespace SimpleAR;

require 'queries/Condition.php';
require 'queries/Arborescence.php';
require 'queries/Option.php';
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
    protected static $_db;

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
    protected static $_isCriticalQuery = false;

    /**
     * The query string.
     *
     * @var string
     */
	protected $_sql;

    /**
     * The query values to bind.
     *
     * @var array.
     */
	protected $_values = array();

    protected $_columns = array();

    /**
     * Use Model properties to construct query?
     *
     * Some query classes can be construct by directly giving table name and table column names. But
     * using model offers more functionalities.
     *
     * @var bool Default true
     */
    /* protected $_useModel  = true; */

    /**
     * Use aliases in queries?
     *
     * @var bool Default true
     */
    //protected $_useAlias  = true;

    /**
     * Root model name.
     *
     * Only used when `$_useModel` is true.
     *
     * @var string
     */
	//protected $_rootModel;

    /**
     * Table object of root model.
     *
     * Only used when `$_useModel` is true.
     *
     * @var Table
     */
	//protected $_rootTable;

    /**
     * Root model table name.
     *
     * @var string
     */
	/* protected $_rootTable = ''; */

    protected static $_options = array();

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
    protected $_context;

    protected $_sth;

    /**
     * Constructor.
     *
     * @param string $root The root model class name or the table name.
     * If $root is a not a class name, `$_useModel` is set to `false` and $root is considered as
     * a table name.
     */
	public function __construct($root)
	{
        $this->_initContext($root);
    }

    /**
     * Allows user to manually set query options.
     *
     * We use __call() magic method in order to avoid code duplication. Without
     * this, we would have to write a method for each available option per query
     * class...
     *
     * This is the matching of Query::_build() method. But _build() is used for
     * automatical query build; __call() is here to open query manipulation to
     * user.
     *
     * @param string $name      Name of the method being called. The method name
     * must correspond to an option name.
     * @param array  $arguments Enumerated array containing the parameters
     * passed to the $name'ed method.
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, static::$_options))
        {
            $arguments = isset($arguments[0]) ? $arguments[0] : null;
            $option    = Query\Option::forge($name, $arguments, $this->_context);

            // There is an option handling function for each available option.
            // They are declared protected in order to Query class to access it.
            $fn = '_' . $s;
            $this->$fn($option);
        }
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
     * @param array $columns The column array. It can take three forms:
     * - an indexed array where values are column names;
     * - an associative array where keys are attribute names and values are
     * column names (for column renaming in result. Select queries only);
     * - a mix of both. Values of numeric entries will be taken as column names.
     *
     * @param string $tableAlias  The table alias to prefix the column with.
     * @param string $resultAlias The result alias to rename the column into.
     *
     * @return array
     */
    public static function columnAliasing($columns, $tableAlias = '', $resultAlias = '')
    {
        $res = array();

        // If a table alias is given, add a dot to respect SQL syntax and to not
        // worry about it in following foreach loop.
        if ($tableAlias)
        {
            $tableAlias = '`' . $tableAlias . '`.';
        }

        // Result alias should only be used by Select queries.
        if ($resultAlias)
        {
            $resultAlias .= '.';
        }

        foreach ($columns as $attribute => $column)
        {
            // $columns is an associative array.
            if (is_string($attribute))
            {
                $res[] = $tableAlias . '`' . $column . '` AS `' . $resultAlias . $attribute . '`';
            }
            // $columns is an indexed array. We do not know the attribute name.
            else
            {
                $res[] = $tableAlias . '`' . $column . '`';
            }
        }

        return $res;
    }


    /**
     * Construct a Count query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Count
     */
	public static function count($options, $root)
	{
        return self::_query('Count', $options, $root);
	}

    /**
     * Construct a Delete query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Delete
     */
	public static function delete($conditions, $root)
	{
        // Delete query only needs a condition array, but we do not want to
        // redefine _build() for this.
        $options = array('conditions' => $conditions);

        return self::_query('Delete', $options, $root);
	}

    public static function init($database)
    {
        self::$_db = $database;
    }

    /**
     * Construct a Insert query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Insert
     */
	public static function insert($options, $root)
	{
        return self::_query('Insert', $options, $root);
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
        return $this->_sth->rowCount();
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

        if ($this->_context->isCriticalQuery)
        {
            if (strpos($this->_sql, ' WHERE ') === false)
            {
                throw new Exception('Cannot execute this query without a WHERE clause.');
            }
        }

        $this->_sth = self::$_db->query($this->_sql, $this->_values);

        return $this;
    }

    /**
     * Construct a Select query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Select
     */
	public static function select($options, $root)
	{
        return self::_query('Select', $options, $root);
	}

    /**
     * Construct a Update query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Update
     */
	public static function update($options, $root)
	{
        return self::_query('Update', $options, $root);
	}

    /**
     * This function builds the query.
     *
     * @param array $options The option array.
     *
     * @return void
     */
	protected function _build(array $options)
    {
        foreach ($options as $name => $value)
        {
            if (in_array($name, static::$_options))
            {
                $option = Query\Option::forge($name, $value, $this->_context);

                $fn = '_' . $name;
                $this->$fn($option);
            }
        }
    }

    protected abstract function _compile();

    protected function _initContext($root)
    {
        $this->_context = new \StdClass();

        // A Model class name is given.
		if (class_exists($root))
		{
            if (! is_subclass_of($root, '\SimpleAR\Model'))
            {
                throw new Exception('Given class "' . $root . '" is not a subclass of Model.');
            }

            $this->_context->useModel = true;
            $this->_context->useAlias = true;

            $this->_context->rootModel       = $root;
            $this->_context->rootTable       = $t = $root::table();
            $this->_context->rootTableName   = $t->name;
            $this->_context->rootTableAlias  = $t->alias;
		}

        // A table name is given.
		else
		{
            // We cannot use models if we only have a table name.
            $this->_context->useModel = false;
            $this->_context->useAlias = true;

            $this->_context->rootTableName   = $root;
            $this->_context->rootTableAlias  = '_' . strtolower($root);
		}

        $this->_context->isCriticalQuery = self::$_isCriticalQuery;
	}

    private static function _query($queryClass, $options, $root)
    {
        $queryClass = "\SimpleAR\Query\\$queryClass";
        $query      = new $queryClass($root);

        $query->_build($options);

        return $query;
    }

}
