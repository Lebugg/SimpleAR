<?php namespace SimpleAR\Database;
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */

require __DIR__ . '/Condition.php';
require __DIR__ . '/Arborescence.php';
require __DIR__ . '/Builder.php';
require __DIR__ . '/Compiler.php';
require __DIR__ . '/JoinClause.php';
require __DIR__ . '/Compiler/BaseCompiler.php';

require __DIR__ . '/Query/Insert.php';
require __DIR__ . '/Query/Where.php';
//require __DIR__ . '/Query/Select.php';
//require __DIR__ . '/Query/Count.php';
//require __DIR__ . '/Query/Update.php';
require __DIR__ . '/Query/Delete.php';

use \SimpleAR\Facades\DB;
use \SimpleAR\Exception;
use \SimpleAR\Database\Compiler\BaseCompiler;

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

    /* protected $_table; */
    /* protected $_tableName; */
    /* protected $_model; */


    /**
     * Is the query built?
     *
     * @var bool
     */
    protected $_built = false;

    /**
     * Is the query compiled?
     */
    protected $_compiled = false;

    /**
     * Has the query been executed?
     *
     * @var bool
     */
    protected $_executed = false;

    /**
     * The list of options to build.
     *
     * It contains a list of Option instances that will be built during query
     * build step.
     *
     * @var array
     */
    protected $_options = array();

    protected $_builder;
    protected $_compiler;

    protected $_useModel = false;
    protected $_useAlias = false;

    /**
     * Connection
     *
     * @var Connection
     */
    protected $_connection;

    public function __construct($root = null)
    {
        $root !== null && $this->root($root);
    }

    /**
     * Set the "root" of the query.
     *
     * It can be:
     *  
     *  * A valid model class name.
     *  * A table name.
     *
     * @param  string $root The query root.
     * @return $this
     */
    public function root($root)
    {
        if (\SimpleAR\is_valid_model_class($root))
        {
            $this->rootModel($root);
        }
        else
        {
            $this->rootTable($root);
        }

        return $this;
    }

    /**
     * Set the model on which to build the query.
     *
     * The function checks that the given class is a subclass of SimpleAR\Model.
     * If not, an Exception will be thrown.
     *
     * @param  string $class The model class.
     * @return $this
     *
     * @throws \SimpleAR\Exception if the class is not a subclass of
     * SimpleAR\Model.
     */
    public function rootModel($class)
    {
        $this->_useModel = true;
        $this->_root = $class;

        return $this;
    }

    /**
     * Set the table on which to execute the query.
     *
     * @param  string $tableName The table name.
     * @return $this
     */
    public function rootTable($tableName)
    {
        $this->_useModel = false;
        $this->_root = $tableName;

        return $this;
    }

    /**
     * Generic option handler.
     *
     * It instanciate an new option according to a name and a value.
     *
     * @param  string $name  The option name.
     * @param  mixed  $value The option value.
     *
     * @return $this
     */
    public function option($name, $value)
    {
        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * Allows user to manually set query options.
     *
     * We use __call() magic method in order to avoid code duplication. Without
     * this, we would have to write a method for each available option per query
     * class...
     *
     * This is the matching of Query::build() method. But build() is used for
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
    public function __call($name, $args)
    {
        if (! isset($args[1]) && isset($args[0]))
        {
            $args = $args[0];
        }

        $this->option($name, $args);

        return $this;
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
        $this->_built    || $this->build($this->_options, $this->getBuilder());
        $this->_compiled || $this->compile($this->getCompiler());

        if (! $this->_executed || $again)
        {
            $this->execute(static::$_isCriticalQuery);
        }

        return $this;
    }

    /**
     * This function builds the query.
     *
     * Actually, it builds all the given options.
     *
     * @param  array $options The option array.
     */
	public function build(array $options, Builder $builder)
    {
        if ($this->_useModel)
        {
            $modelClass = $this->_root;
            $builder->setTable($modelClass::table());
        }

        $builder->build($this, $options);
        $this->_built = true;
    }

    /**
     * This function compile all pieces of the query into a well-formated SQL
     * string.
     *
     * In the same time, $_values has to contain values in correct order so that
     * run() can safely be called.
     *
     * @return void.
     */
    protected function compile(Compiler $compiler)
    {
        $compiler->useTablePrefix = $this->_useAlias;

        $this->_sql = $compiler->compile($this);
        $this->_compiled = true;
    }

    public function execute($checkSafety = false)
    {
        $sql = $this->getSql();

        if ($checkSafety && ! $this->queryIsSafe($sql))
        {
            // No WHERE condition for critical query? Do not process.
            throw new Exception('Cannot execute this query without a WHERE clause.');
        }

        // At last, execute the query.
        $this->executeQuery($this->getConnection(), $sql, $this->getValues());
        $this->_executed = true;
    }

    public function queryIsSafe($sql)
    {
        return strpos($sql, ' WHERE ') !== false;
    }

    public function executeQuery(Connection $conn, $sql, array $values)
    {
        return $conn->query($sql, $values);
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
    public function columnAliasing(array $columns, $tableAlias = '', $resultAlias = '')
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

    public function getSql()
    {
        return $this->_sql;
    }

    public abstract function getValues();

    public function getBuilder()
    {
        return $this->_builder = $this->_builder ?: $this->_newBuilder();
    }

    public function getCompiler()
    {
        return $this->_compiler = $this->_compiler ?: $this->_newCompiler();
    }

    public function getConnection()
    {
        return $this->_connection = $this->_connection ?: DB::connection();
    }

    protected abstract function _newBuilder();

    protected function _newCompiler()
    {
        return new BaseCompiler();
    }
}
