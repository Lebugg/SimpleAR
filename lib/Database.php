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
 *  $oDb->query($sQuery, $aParams);
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
    private $_oPdo;

    /**
     * The current/last PDO Statement used.
     * @link http://www.php.net/manual/en/class.pdostatement.php The
     * PDOStatement class documentation.
     *
     * @var PDOStatement
     */
    private $_oSth;

    /**
     * Are we in debug mode?
     *
     * @var bool
     */
    private $_bDebug;

    /**
     * Database name.
     *
     * @var string
     */
    private $_sDatabase;

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
     *  $oDbInstance->queries();
     *  ```
     *
     * @see Database::queries()
     * @var array
     */
    private $_aQueries = array();

    /**
     * Constructor.
     *
     * @param Config $oConfig The configuration object. Database is instanciate by SimpleAR.php.
     *
     * Used PDO configuration:
     *
     * * PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
     *
     * @see SimpleAR.php
     * @see http://www.php.net/manual/en/pdo.construct.php
     */
    public function __construct($oConfig)
    {
        $a    = $oConfig->dsn;
        $sDsn = $a['driver'].':host='.$a['host'] .';dbname='.$a['name'] .';charset='.$a['charset'].';';

        $aOptions = array();
        $aOptions[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
        //$aOptions[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';

        try
        {
            $this->_oPdo = new \PDO($sDsn, $a['user'], $a['password'], $aOptions);
        }
        catch (\PDOException $oEx)
		{
            throw new DatabaseException($oEx->getMessage(), null, $oEx);
        }

        $this->_sDatabase = $a['name'];
        $this->_bDebug    = $oConfig->debug;
    }

    /**
     * Adapter to PDO::beginTransaction()
     *
     * @see http://www.php.net/manual/en/pdo.begintransaction.php
     */
    public function beginTransaction()
    {
        $this->_oPdo->beginTransaction();
    }

    /**
     * Database name getter.
     *
     * @return string The current database name.
     */
    public function database()
    {
        return $this->_sDatabase;
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
        return $this->_oPdo->lastInsertId();
    }

    /**
     * Executes a query.
     *
     * Actually, it prepares and executes the request in two steps. It provides
     * security against SQL injection.
     *
     * @param string $sQuery  The SQL query.
     * @param array  $aParams The query parameters.
     *
     * @return PDOStatement
     */
    public function query($sQuery, $aParams = array())
    {
        if ($this->_bDebug)
        {
            $time = microtime(TRUE);
        }

        try
        {
            $oSth = $this->_oPdo->prepare($sQuery);
            $oSth->execute((array) $aParams);

            if ($this->_bDebug)
            {
                $sQueryDebug  = $sQuery;
                $aParamsDebug = $aParams;

                //$s = str_replace(array_pad(array(), count($aParams), '?'), $aParams, $sQuery);
                $sQueryDebug = preg_replace_callback( '/\?/', function( $match) use( &$aParamsDebug) {
                    if (!is_array($aParamsDebug)) {
                        $aParamsDebug = array($aParamsDebug);
                    }
                    return var_export(array_shift($aParamsDebug), true);
                }, $sQueryDebug);

                $this->_aQueries[] = array(
                    'sql'  => $sQueryDebug,
                    'time' => (microtime(TRUE) - $time) * 1000,
                );
            }

        }
        catch (\PDOException $oEx)
        {
            throw new DatabaseException($oEx->getMessage(), $sQuery, $oEx);
        }

        return $oSth;
    }

    /**
     * Adapter to PDO::rollBack()
     *
     * @see http://www.php.net/manual/en/pdo.rollback.php
     */
    public function rollBack()
    {
        $this->_oPdo->rollBack();
    }


    /**
     * Executed queries getter.
     *
     * @return array The executed queries.
     * @see Database::$_aQueries for further information on how returned array is constructed.
     */
    public function queries()
    {
        return $this->_aQueries;
    }
}
