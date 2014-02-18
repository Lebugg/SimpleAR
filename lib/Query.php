<?php namespace SimpleAR;
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */

require __DIR__ . '/Query/Condition.php';
require __DIR__ . '/Query/Arborescence.php';
require __DIR__ . '/Query/Option.php';
require __DIR__ . '/Query/Where.php';
require __DIR__ . '/Query/Insert.php';
require __DIR__ . '/Query/Select.php';
require __DIR__ . '/Query/Count.php';
require __DIR__ . '/Query/Update.php';
require __DIR__ . '/Query/Delete.php';

use \SimpleAR\Facades\DB;

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
     * Available options for this query class.
     *
     * @var array
     */
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

    /**
     * Statement object of the last executed query.
     *
     * @var PDOStatement
     */
    protected $_sth;

    /**
     * Has the query been executed?
     *
     * @var bool
     */
    protected $_executed = false;

    /**
     * Constructor.
     *
     * It just calls _initContext().
     *
     * @param string $root The root model class name or the table name.
     *
     * @see SimpleAR\Query::_initContext()
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
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, static::$_options))
        {
            $arguments = isset($arguments[0]) ? $arguments[0] : null;
            $option    = Query\Option::forge($name, $arguments, $this->_context);

            // There is an option handling function for each available option.
            // They are declared protected in order to Query class to access it.
            $fn = '_' . $name;
            $this->$fn($option);
        }

        return $this;
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
    public static function columnAliasing(array $columns, $tableAlias = '', $resultAlias = '')
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
	public static function count($root, array $options = null)
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
	public static function delete($root, array $conditions = null)
	{
        // Delete query only needs a condition array, but we do not want to
        // redefine _build() for this.
        $options = array('conditions' => $conditions);

        return self::_query('Delete', $options, $root);
	}

    /**
     * Construct a Insert query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Insert
     */
	public static function insert($root, array $options = null)
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
        $this->run();
        return $this->_sth->rowCount();
    }

    /**
     * Execute the Query.
     *
     * @see http://www.php.net/manual/en/pdostatement.execute.php
     *
     * For queries that are "critical", there must be a WHERE clause in the
     * query string. Otherwise, an exception will be thrown.
     *
     * @param  bool $redo If true, run it even if it already did.
     * @return $this
     */
    public function run($again = false)
    {
        if ($this->_executed && ! $again)
        {
            return $this;
        }

        if (! $this->_executed)
        {
            $this->_compile();

            // No WHERE condition for critical query? Do not process.
            if ($this->_context->isCriticalQuery)
            {
                if (strpos($this->_sql, ' WHERE ') === false)
                {
                    throw new Exception('Cannot execute this query without a WHERE clause.');
                }
            }
        }

        // At last, execute the query.
        $this->_sth = DB::query($this->_sql, $this->_values);

        // Remember!
        $this->_executed = true;

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
	public static function select($root, array $options = null)
	{
        return self::_query('Select', (array) $options, $root);
	}

    /**
     * Construct a Update query.
     *
     * @param array $options The option array.
     * @param string $root The root model class name or the table name.
     *
     * @return Query\Update
     */
	public static function update($root, array $options = null)
	{
        return self::_query('Update', $options, $root);
	}

    /**
     * This function builds the query.
     *
     * Actually, it 
     *
     * @param array $options The option array.
     *
     * @return $this
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

        return $this;
    }

    /**
     * This function compile all pieces of the query into a well-formated SQL
     * string.
     *
     * In the same time, $_values has to contains every value in correct order
     * so that run() can safely be called.
     *
     * @return void.
     */
    protected abstract function _compile();

    /**
     * Initialize the query build context.
     *
     * The context will be used at almost every step of the query.
     *
     * @param string $root The name of the class that called the query or the
     * name of the table on which to execute the query.
     *
     * @see SimpleAR\Query::$_context for more information on query build
     * context.
     *
     * @return void
     */
    protected function _initContext($root)
    {
        $context = new \StdClass();

        // A Model class name is given.
		if (class_exists($root))
		{
            // Oh, you made a mistake.
            if (! is_subclass_of($root, '\SimpleAR\Model'))
            {
                throw new Exception('Given class "' . $root . '" is not a subclass of Model.');
            }

            $context->useModel = true;
            $context->useAlias = true;

            $context->rootModel       = $root;
            $context->rootTable       = $t = $root::table();
            $context->rootTableName   = $t->name;
            $context->rootTableAlias  = $t->alias;
		}

        // A table name is given.
		else
		{
            // We cannot use models if we only have a table name.
            $context->useModel = false;
            $context->useAlias = true;

            $context->rootTableName   = $root;
            $context->rootTableAlias  = '_' . strtolower($root);
		}

        // Careful!
        $context->isCriticalQuery = self::$_isCriticalQuery;

        $this->_context = $context;
	}

    /**
     * Instanciate the actual query.
     *
     * @param string $class     A valid query class name.
     * @param array  $options   The option array.
     * @param string $root      The root Model or table name.
     *
     * @return Query
     */
    private static function _query($class, array $options, $root)
    {
        $class = "\SimpleAR\Query\\$class";
        $query = new $class($root);

        return $query->_build($options);
    }

}
