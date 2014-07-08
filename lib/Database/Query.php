<?php namespace SimpleAR\Database;
/**
 * This file contains the Query class that is the main class to manipulate SQL queries.
 *
 * It handles subclasses and Condition class includes.
 */

require __DIR__ . '/Condition.php';
require __DIR__ . '/Builder.php';
require __DIR__ . '/Compiler.php';
require __DIR__ . '/JoinClause.php';
require __DIR__ . '/Compiler/BaseCompiler.php';

use \SimpleAR\Exception;

use \SimpleAR\Database\Builder;
use \SimpleAR\Database\Compiler;
use \SimpleAR\Database\Connection;

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
    public $components = array();

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

    public function getSql()
    {
        return $this->_sql;
    }

    public function getValues()
    {
        $val = $this->_builder->getValues();
        $val = $this->prepareValuesForExecution($val);

        return $val;
    }

    public function getBuilder()
    {
        return $this->_builder;
    }

    public function setBuilder(Builder $b)
    {
        $this->_builder = $b;
    }

    /**
     * Get compiler instance.
     *
     * @return Compiler
     */
    public function getCompiler()
    {
        return $this->_compiler;
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
        $this->_built    || $this->build();
        $this->_compiled || $this->compile();

        if (! $this->_executed || $again)
        {
            $this->execute($this->isCriticalQuery());
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
	public function build()
    {
        $this->components = $this->getBuilder()->build();
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
    protected function compile()
    {
        if (! $this->_built)
        {
            throw new Exception('Cannot compile query: it is not built.');
        }

        //$compiler->useTablePrefix = $this->_useAlias;

        $this->_sql = $this->getCompiler()->compile($this, $this->getBuilder()->type);
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
        $this->executeQuery($sql, $this->getValues());
        $this->_executed = true;
    }

    public function queryIsSafe($sql)
    {
        return strpos($sql, ' WHERE ') !== false;
    }

    public function executeQuery($sql, array $values)
    {
        return $this->getConnection()->query($sql, $values);
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
            // Discard Expression. It is directly written in SQL.
            if ($value instanceof Expression) { continue; }

            elseif (is_array($value))
            {
                if (is_array($value[0]))
                {
                    $res = array_merge($res, call_user_func_array('array_merge', $value));
                }
                else
                {
                    $res = array_merge($res, $value);
                }
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
