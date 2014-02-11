<?php
/**
 * This file contains the Database class.
 *
 * @author Lebugg
 */
namespace SimpleAR;

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
class Database
{
    /**
     * The PDO object.
     * @link http://www.php.net/manual/en/class.pdo.php The PDO documentation.
     *
     * @var PDO
     */
    private $_pdo;

    /**
     * The current/last PDO Statement used.
     * @link http://www.php.net/manual/en/class.pdostatement.php The
     * PDOStatement class documentation.
     *
     * @var PDOStatement
     */
    private $_sth;

    /**
     * Are we in debug mode?
     *
     * @var bool
     */
    private $_debug;

    /**
     * Database name.
     *
     * @var string
     */
    private $_database;

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
    public function __construct($config)
    {
        $a    = $config->dsn;
        $dsn = $a['driver'].':host='.$a['host'] .';dbname='.$a['name'] .';charset='.$a['charset'].';';

        $options = array();
        $options[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
        //$options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';

        try
        {
            $this->_pdo = new \PDO($dsn, $a['user'], $a['password'], $options);
        }
        catch (\PDOException $ex)
		{
            throw new DatabaseException($ex->getMessage(), null, $ex);
        }

        $this->_database = $a['name'];
        $this->_debug    = $config->debug;
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
     * Gets the last inserted ID.
     *
     * Warning: Works for MySQL, but it won't for PostgreSQL.
     *
     * @return int The last inserted ID.
     */
    public function lastInsertId()
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * Returns the PDO object used by this instance.
     *
     * @return \PDO
     */
    public function pdo()
    {
        return $this->_pdo;
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
            $sth = $this->_pdo->prepare($query);
            $sth->execute((array) $params);

            if ($this->_debug)
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
                    'time' => (microtime(TRUE) - $time) * 1000,
                );
            }

        }
        catch (\PDOException $ex)
        {
            throw new DatabaseException($ex->getMessage(), $query, $ex);
        }

        return $sth;
    }

    /**
     * Adapter to PDO::rollBack()
     *
     * @see http://www.php.net/manual/en/pdo.rollback.php
     */
    public function rollBack()
    {
        $this->_pdo->rollBack();
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
}
