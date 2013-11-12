<?php
namespace SimpleAR;

/**
 * This file contains the DataBase class.
 *
 * @author Damien Launay
 */

/**
 * This class abstracts a data base connection.
 *
 * It follows a Singleton pattern. It is not vulnerable to SQL injection, every
 * request is processed by binding parameters to it. So use parameters binding
 * every time you can in your SQL strings.
 *
 * There is two way of executing a request:
 * 1) The quick way:
 *      $oDb->query($sQuery, $aParams);
 * 2) The two-steps way:
 *      $oDb->prepare($sQuery);
 *      $oDb->execute($aParams);
 *
 * The second method allows you to execute several times your query. Actually, 
 * you could call execute() after query(), but don't do this.
 *
 * Every request is cached is an PHP array. This way, if you try to execute the 
 * same request in different places of your script, it won't bother to call 
 * database to prepare the query a second time, it will simply fetch the 
 * corresponding prepared statement stored in caching array.
 *
 * @author Damien Launay
 * @package core
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
     * The static instance of the class for Singleton
     * pattern.
     *
     * @var DataBase
     */
    private static $_oInstance = NULL;

    /**
     * Array that caches prepared requests.
     *
     * @var array
     */
    private $_aSth = array();

    /**
     * The current/last PDO Statement used.
     * @link http://www.php.net/manual/en/class.pdostatement.php The
     * PDOStatement class documentation.
     *
     * @var PDOStatement
     */
    private $_oSth;

    private $_bDebug;
    private $_sDatabase;
    private $_aQueries    = array();
    private $_aQueryTimes = array();

    /**
     * Constructor.
     */
    private function __construct()
    {
        $oConfig = Config::instance();
        $aDsn    = $oConfig->dsn;

        $sDsn                                    = $aDsn['driver'].':host='.$aDsn['host'] .';dbname='.$aDsn['name'] .';charset='.$aDsn['charset'].';';
        $aOptions                                = array();
        $aOptions[\PDO::ATTR_ERRMODE]            = \PDO::ERRMODE_EXCEPTION;
        $aOptions[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES \'UTF8\'';

        try {
            $this->_oPdo = new \PDO($sDsn, $aDsn['user'], $aDsn['password'], $aOptions);
        } catch (\PDOException $oEx)
		{
            throw new DatabaseException($oEx->getMessage(), null, $oEx);
        }

        $this->_sDatabase = $aDsn['name'];
        $this->_bDebug    = $oConfig->debug;
    }

    /**
     * Executes a prepared statement.
     *
     * @param array $aParams The parameters to bind to the SQL request.
     *
     * @return PDOStatement
     */
    public function execute($aParams = array())
    {
        if ($this->_oSth) {
            $this->_oSth->execute((array) $aParams);
            return $this->_oSth;
        }

        return FALSE;
    }

    /**
     * Singleton implementation. Returns the Singleton instance.
     *
     * @return DataBase
     */
    public static function instance()
    {
        if (self::$_oInstance === NULL) {
            self::$_oInstance = new DataBase();
        }

        return self::$_oInstance;
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
     * Prepares an SQL statement.
     *
     * @param string $sQuery The SQL query to prepare.
     *
     * @return void
     */
    public function prepare($sQuery)
    {
		if ($this->_bDebug)
		{
		}

        try {
            if (isset($this->_aSth[$sQuery])) {
                $this->_oSth = $this->_aSth[$sQuery];
            } else {
                $this->_oSth        = $this->_oPdo->prepare($sQuery);
                $this->_aSth[$sQuery] = $this->_oSth;
            }
        } catch (\PDOException $oEx) {
            throw new DatabaseException($oEx->getMessage(), $sQuery, $oEx);
        }

        return $this->_oSth;
    }

    /**
     * Executes a query.
     *
     * Actually, it prepares and executes the request in two steps. It provides
     * security against SQL injection.
     *
     * @param string $sQuery The SQL query.
     * @param array $aParams The query parameters.
     *
     * @return PDOStatement
     */
    public function query($sQuery, $aParams = array())
    {
        if ($this->_bDebug) {
            $sQueryDebug  = $sQuery;
            $aParamsDebug = $aParams;

            //$s = str_replace(array_pad(array(), count($aParams), '?'), $aParams, $sQuery);
            $sQueryDebug = preg_replace_callback( '/\?/', function( $match) use( &$aParamsDebug) {
                if (!is_array($aParamsDebug)) {
                    $aParamsDebug = array($aParamsDebug);
                }
                return array_shift($aParamsDebug);
            }, $sQueryDebug);

            $this->_aQueries[] = $sQueryDebug;
            $time = microtime(TRUE);
        }

        try {

            $oSth = $this->prepare($sQuery);
            $this->_oSth->execute((array) $aParams);

            if ($this->_bDebug) {
                $this->_aQueryTimes[] = (microtime(TRUE) - $time) * 1000;
            }
        } catch (\PDOException $oEx) {
            throw new DatabaseException($oEx->getMessage(), $sQuery, $oEx);
        }

        return $this->_oSth;
    }

    // FOR CI.

    public function database()
    {
        return $this->_sDatabase;
    }
    public function queries()
    {
        return $this->_aQueries;
    }
    public function queryTimes()
    {
        return $this->_aQueryTimes;
    }
}
