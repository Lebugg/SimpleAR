<?php namespace SimpleAR\Database;

require __DIR__ . '/Compiler.php';

use \SimpleAR\Config;
use \SimpleAR\Database\Compiler\BaseCompiler;
use \SimpleAR\Exception\Database as DatabaseEx;

/**
 * This class abstracts a database connection.
 *
 * It follows a Singleton pattern. It is not vulnerable to SQL injection, every
 * request is processed by binding parameters to it. So use parameters binding
 * every time you can in your SQL strings.
 *
 * How to execute a query:
 *  ```php
 *  $db->query($query, $params);
 *  ```
 */
class Connection
{
    /**
     * The PDO object.
     * @link http://www.php.net/manual/en/class.pdo.php The PDO documentation.
     *
     * @var PDO
     */
    protected $_pdo;

    /**
     * The current/last PDO Statement used.
     * @link http://www.php.net/manual/en/class.pdostatement.php The
     * PDOStatement class documentation.
     *
     * @var PDOStatement
     */
    protected $_sth;

    /**
     * Are we in debug mode?
     *
     * @var bool
     */
    protected $_debug;

    /**
     * Database name.
     *
     * @var string
     */
    protected $_database;

    /**
     * Database driver.
     *
     * @var string
     */
    protected $_driver;

    /**
     * Executed query array.
     *
     * This array contains every executed queries. It is constructed as follows:
     *  ```php
     *  array(
     *      // First query executed.
     *      array(
     *          'sql'  => <The SQL query string>,
     *          'time' => <The query execution time in milliseconds>
     *      ),
     *      // Second query.
     *      array(
     *          ...
     *      ),
     *      ...,
     *  )
     *  ```
     *
     * You can retrieve this array with the following:
     *  ```php
     *  $dbInstance->queries();
     *  ```
     *
     * @see Database::queries()
     * @var array
     */
    private $_queries = array();

    /**
     * The Compiler to use with this connection.
     *
     * @var Compiler
     */
    protected $_compiler;

    /**
     * Constructor.
     *
     * @param Config $config The configuration object. Database is instanciate by SimpleAR.php.
     *
     * Used PDO configuration:
     *
     * * PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
     *
     * @see SimpleAR.php
     * @see http://www.php.net/manual/en/pdo.construct.php
     */
    public function __construct(Config $config = null)
    {
        $config && $this->configure($config);
    }

    public function configure(Config $config)
    {
        $config->dsn && $this->connect($config->dsn);

        $this->_debug    = $config->debug;
    }

    /**
     * Connect to database according to given information.
     *
     * @param array $dsn An array containing DB information.
     */
    public function connect(array $dsn)
    {
        $a    = $dsn;
        $dsn = $a['driver'].':host='.$a['host'] .';dbname='.$a['name'] .';charset='.$a['charset'].';';

        $options = array();
        $options[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
        //$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';

        try
        {
            $this->setPDO(new \PDO($dsn, $a['user'], $a['password'], $options));
        }
        catch (\PDOException $ex)
		{
            throw new DatabaseEx($ex->getMessage(), null, null);
        }

        $this->_database = $a['name'];
        $this->_driver   = $a['driver'];
    }

    public function getPDO()
    {
        return $this->_pdo;
    }

    /**
     * Returns the PDO object used by this instance.
     *
     * @return \PDO
     */
    public function pdo()
    {
        return $this->getPDO();
    }

    public function setPDO(\PDO $pdo)
    {
        $this->_pdo = $pdo;
    }

    /**
     * Adapter to PDO::beginTransaction()
     *
     * @see http://www.php.net/manual/en/pdo.begintransaction.php
     */
    public function beginTransaction()
    {
        $this->_pdo->beginTransaction();
    }

    /**
     * Database name getter.
     *
     * @return string The current database name.
     */
    public function database()
    {
        return $this->_database;
    }

    /**
     * Executes a query.
     *
     * Actually, it prepares and executes the request in two steps. It provides
     * security against SQL injection.
     *
     * @param string $query  The SQL query.
     * @param array  $params The query parameters.
     *
     * @return PDOStatement
     */
    public function query($query, $params = array())
    {
        if ($this->_debug)
        {
            $time = microtime(TRUE);
        }

        try
        {
            if (! $this->_pdo) { throw new \Exception; }
            $sth = $this->getPDO()->prepare($query);
            $sth->execute((array) $params);
            $this->_sth = $sth;

            $this->_debug && $this->logQuery($query, $params, (microtime(TRUE) - $time));

        }
        catch (\PDOException $ex)
        {
            // We must wait for PHP 5.5 to be able to use `finally` block.
            if ($this->_debug)
            {
                $this->logQuery($query, $params, 0);

                // If we debug is on, we use debug query in exception message.
                $log = end($this->_queries);
                $query = $log['sql'];
            }

            $log = end($this->_queries);
            throw new DatabaseEx($ex->getMessage(), $log['sql'], $ex);
        }

        return $sth;
    }

    public function select($query, $params = array())
    {
        $sth = $this->query($query, $params);
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert($query, $params = array())
    {
        $sth = $this->query($query, $params);
        return $this->getPDO()->lastInsertId();
    }

    public function delete($query, $params = array())
    {
        $sth = $this->query($query, $params);
        return $sth->rowCount();
    }

    public function update($query, $params = array())
    {
        $sth = $this->query($query, $params);
        return $sth->rowCount();
    }

    /**
     * Log given query.
     *
     * @param string $query The raw SQL string.
     * @param array  $params The bound parameters.
     * @param float  $time The query execution time in seconds.
     *
     * Queries will be stored in $_queries property as an array containing these
     * entries:
     *
     *  * "sql" The SQL string where wilcards have been transpolated with bound
     *  params;
     *  * "time": The query execution time, in milliseconds.
     */
    public function logQuery($query, array $params, $time)
    {
        $queryDebug  = $query;
        $paramsDebug = $params;

        //$s = str_replace(array_pad(array(), count($params), '?'), $params, $query);
        $queryDebug = preg_replace_callback( '/\?/', function( $match) use( &$paramsDebug) {
            if (!is_array($paramsDebug)) {
                $paramsDebug = array($paramsDebug);
            }
            return var_export(array_shift($paramsDebug), true);
        }, $queryDebug);

        $this->_queries[] = array(
            'sql'  => $queryDebug,
            'time' => $time * 1000,
        );
    }

    /**
     * Adapter to PDO::rollBack()
     *
     * @see http://www.php.net/manual/en/pdo.rollback.php
     */
    public function rollBack()
    {
        $this->getPDO()->rollBack();
    }

    public function getDriver()
    {
        return $this->_driver;
    }

    /**
     * Executed queries getter.
     *
     * @return array The executed queries.
     * @see Database::$_queries for further information on how returned array is constructed.
     */
    public function queries()
    {
        return $this->_queries;
    }

    /**
     * Gets the last inserted ID.
     *
     * Warning: Works for MySQL, but it won't for PostgreSQL.
     *
     * @return int The last inserted ID.
     */
    public function lastInsertId()
    {
        if (! $this->_pdo) throw new \Exception;
        return $this->getPDO()->lastInsertId();
    }

    /**
     * Get the number of rows affected by the last SQL statement.
     *
     * @see http://www.php.net/manual/en/pdostatement.rowcount.php
     */
    public function rowCount()
    {
        return $this->_sth->rowCount();
    }

    /**
     * Return next fetched row from DB.
     *
     * @param bool $next Set it to false to get previous row instead on next
     * row. (Default: true)
     *
     * @return array
     */
    public function getNextRow($next = true)
    {
        $ori = $next ? \PDO::FETCH_ORI_NEXT : \PDO::FETCH_ORI_PRIOR;
        if (! $this->_sth) { throw new \Exception; }
        return $this->_sth->fetch(\PDO::FETCH_ASSOC, $ori);
    }

    public function fetchAll()
    {
        if (! $this->_sth) { throw new \Exception; }
        return $this->_sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fetch the last found row from DB.
     *
     * @return array
     */
    public function getLastRow()
    {
        if (! $this->_sth) { throw new \Exception; }
        return $this->_sth->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_LAST);
    }

    /**
     * Return next value for column of given index.
     *
     * @param int $index The column index.
     * @return mixed The column value.
     */
    public function getColumn($index = 0)
    {
        return $this->_sth->fetch(\PDO::FETCH_COLUMN, $index);
    }

    /**
     * Set the compiler according to database driver.
     *
     * If no adequate compiler is found for the driver, default compiler is
     * used.
     *
     * @see setCompiler()
     * @see setDefaultCompiler()
     */
    public function chooseCompiler()
    {
        $specificCompiler = ucfirst($this->getDriver()) . 'Compiler';
        $specificCompiler = 'SimpleAR\Database\Compiler\\' . $specificCompiler;

        if (class_exists($specificCompiler)) {
            $this->setCompiler(new $specificCompiler);
        } else {
            $this->setDefaultCompiler();
        }
    }

    /**
     * Return the Compiler instance of this connection.
     *
     * @return Connection
     */
    public function getCompiler()
    {
        $this->_compiler || $this->chooseCompiler();

        return $this->_compiler;
    }

    /**
     * Set the compiler.
     *
     * @param Compiler $compiler
     */
    public function setCompiler(Compiler $compiler)
    {
        $this->_compiler = $compiler;
    }

    /**
     * Set default compiler.
     *
     * @see setCompiler()
     * @see Database\Compiler\BaseCompiler
     */
    public function setDefaultCompiler()
    {
        $this->setCompiler(new BaseCompiler);
    }

}
