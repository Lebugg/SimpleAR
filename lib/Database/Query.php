<?php namespace SimpleAR\Database;
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */

require __DIR__ . '/Builder.php';
require __DIR__ . '/Compiler.php';
require __DIR__ . '/JoinClause.php';
require __DIR__ . '/Compiler/BaseCompiler.php';

use \SimpleAR\Exception;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Compiler;
use \SimpleAR\Database\Connection;
// To be removed as soon as getCompiler() does not use DB.
use \SimpleAR\Facades\DB;

/**
 * This class is the superclass of all SQL queries.
 *
 */
class Query
{

    /**
     * Component array.
     *
     * Query class is also a container. Builders fill this array with 
     * appropriate components that Compiler will use to construct SQL.
     *
     * @var array
     */
    protected $_components = array();
    protected $_componentValues;

    /**
     * Is this query class critical?
     *
     * A critical query cannot be executed without a WHERE clause.
     *
     * @var bool false
     */
    protected $_isCriticalQuery = false;

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
     * The builder instance.
     *
     * @var Builder
     */
    protected $_builder;

    /**
     * The compiler instance.
     *
     * @var Compiler
     */
    protected $_compiler;

    /**
     * Connection
     *
     * @var Connection
     */
    protected $_connection;

    /**
     * The query type: Select, Update, Delete, Insert.
     *
     * It corresponds to the builder type. It is set at the same time as the
     * builder.
     *
     * @var string
     *
     * @see setBuilder()
     */
    protected $_type;

    public function __construct(Builder $builder = null,
        Compiler $compiler = null,
        Connection $conn = null,
        $criticalQuery = false
    ) {
        $builder && $this->setBuilder($builder);
        $this->_compiler = $compiler;
        $this->_connection = $conn;

        $this->setCriticalQuery($criticalQuery);
    }

    /**
     * Get the SQL representation of the query.
     *
     * Query will be automatically compiled if it is not yet.
     *
     * @return string SQL
     */
    public function getSql()
    {
        $this->_compiled || $this->compile();

        return $this->_sql;
    }

    /**
     * Get values that are to be bind to query at execution.
     *
     * Query will be automatically built if it is not yet.
     *
     * @return array The flat value array
     */
    public function getValues()
    {
        //$this->_built || $this->build();
        $this->_compiled || $this->compile();

        // $val = $this->_builder->getValues();
        // $val = $this->prepareValuesForExecution($val);
        return $this->_values;
    }

    /**
     * Return query components.
     *
     * @return array
     */
    public function getComponents()
    {
        return $this->_components;
    }

    /**
     * Set a component.
     *
     * @param string $type The component type/name.
     * @param mixed  $value The component's value.
     */
    public function setComponent($type, $value)
    {
        $this->_components[$type] = $value;
    }

    /**
     * Return query components' values.
     *
     * Component values are different from plain values because they are grouped 
     * by component.
     *
     * Example:
     * --------
     *
     * For an Update query, $q->set('field', 1)->where('id' => 2);
     *
     * Plain values: [1, 2].
     * Component values: ['set' => [1], 'where' => [2]].
     *
     * It prevents values order to be mixed up.
     *
     * @return array
     */
    public function getComponentValues()
    {
        // Component values are given by Builder.
        $this->_built || $this->build();

        return $this->_componentValues;
    }

    /**
     * Return the query's type.
     *
     * Query's type depends on the used builder's type. It is set in 
     * setBuilder().
     *
     * @return string The query type.
     *
     * @see setBuilder()
     * @see $_type
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Get the query builder.
     *
     * @return Builder
     */
    public function getBuilder()
    {
        return $this->_builder;
    }

    /**
     * Set the query builder.
     *
     * @param Builder $builder
     */
    public function setBuilder(Builder $builder)
    {
        $this->_builder = $builder;
        $this->_type = $builder->type;
    }

    /**
     * Get compiler instance.
     *
     * @return Compiler
     */
    public function getCompiler()
    {
        // Te be change. We *have* to use Connections.
        return $this->_compiler = ($this->_compiler ?: DB::compiler());
    }

    /**
     * Get connection instance.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    public function isCriticalQuery()
    {
        return $this->_isCriticalQuery;
    }

    public function setCriticalQuery($bool = true)
    {
        $this->_isCriticalQuery = $bool;
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
        $this->build();
        $this->compile();
        $this->execute($this->isCriticalQuery());

        return $this;
    }

    /**
     * This function builds the query.
     *
     * Actually, it builds all the given options.
     * After build step, query values can be retrieved with `getValues()`.
     *
     * @param  array $options The option array.
     * @return $this
     */
	public function build()
    {
        $builder = $this->getBuilder();
        $this->_components = $builder->build();
        $this->_componentValues = $builder->getValues();

        $this->_built = true;

        return $this;
    }

    /**
     * This function compile all pieces of the query into a well-formated SQL
     * string.
     *
     * In the same time, $_values has to contain values in correct order so that
     * run() can safely be called.
     *
     * After compilation, query's SQL can be retrieved with `getSql()`.
     *
     * @return void.
     */
    protected function compile()
    {
        $this->_built || $this->build();

        list($this->_sql, $this->_values) = $this->getCompiler()->compile($this);

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
        $values = $this->prepareValuesForExecution($this->getValues());
        $this->executeQuery($sql, $values);
        $this->_executed = true;
    }

    public function queryIsSafe($sql)
    {
        return strpos($sql, ' WHERE ') !== false;
    }

    /**
     * Execute the SQL query.
     *
     * @param string $sql The SQL query.
     * @param array  $values The values to bind to query.
     *
     * @see \SimpleAR\Database\Connection::query()
     */
    public function executeQuery($sql, array $values)
    {
        $this->getConnection()->query($sql, $values);
    }

    /**
     * This function is used to format value array for PDO.
     *
     * @return array
     */
    public function prepareValuesForExecution(array $values)
    {
        $res = array();

        foreach ($values as $value)
        {
            // Expression values are directly inserted in the SQL string. We do
            // not need to include them to the values-to-bind list.
            if ($value instanceof Expression) { continue; }

            // This allows users to directly pass object or object array as
            // a condition value.
            //
            // The same check is done in Compiler to correctly construct
            // query.
            // @see SimpleAR\Database\Compiler::parameterize()
            if ($value instanceof \SimpleAR\Orm\Model)
            {
                $value = $value->id();
            }

            if (is_array($value))
            {
                if (! $value) { continue; }

                else
                {
                    $res = array_merge($res, $this->prepareValuesForExecution($value));
                }

                // elseif (is_array($value[0]))
                // {
                //     $res = array_merge($res, call_user_func_array('array_merge', $value));
                // }
                //
                // else
                // {
                //     $res = array_merge($res, $value);
                // }
            }

            else
            {
                $res[] = $value;
            }
        }

        return $res;
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
        return $this->getConnection()->rowCount();
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
     * @param string $name Name of the method being called. The method name
     * must correspond to an option name.
     * @param array  $arguments Enumerated array containing the parameters
     * passed to the $name'd method.
     *
     * @return $this
     */
    public function __call($name, $args)
    {
        $b = $this->getBuilder();
        switch (count($args))
        {
            case 0: $b->$name(); break;
            case 1: $b->$name($args[0]); break;
            case 2: $b->$name($args[0], $args[1]); break;
            case 3: $b->$name($args[0], $args[1], $args[2]); break;
            case 4: $b->$name($args[0], $args[1], $args[2], $args[3]); break;
            default: call_user_func_array(array($b, $name), $args);
        }

        return $this;
    }

}
