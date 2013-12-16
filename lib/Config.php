<?php
/**
 * This file contains Config class.
 * 
 * @author Lebugg lebugg@hotmail.com
 */
namespace SimpleAR;

/**
 * Class Config.
 *
 * This class implements Singleton pattern. It handles all SimpleAR
 * configuration options. Each member is an option.
 *
 * Example:
 * If a configuration option is called "charset", then Config class has a
 * `$_charset` member attribute. To get and set charset option, use:
 *
 * ```php
 * // Set
 * $oConfig->charset = 'utf8';
 *
 * // Get
 * $sCharset = $oConfig->charset;
 * ```
 *
 * Config class implements `__get()` and `__set()` magic methods to get and set
 * options. This allows developper to add a specific setter for an option by
 * creating a function called by the *option* name.
 *
 * Example: for the "charset" option, you can write a `charset($sCharset)`
 * function that will handle `Config::$_charset`
 * set:
 *
 * ```php
 * public function charset($sCharset)
 * {
 *     if (in_array($sCharset, array('utf8', 'latin1')))
 *     {
 *         $this->_charset = $sCharset;
 *     }
 *     else
 *     {
 *         throw new Exception('Wrong charset!');
 *     }
 * }
 * ```
 *
 * Only one option is required: `Config::$_dsn`.
 *
 * More documentation on options is available in class members' comment
 * sections.
 *
 */
class Config
{
   
    /**
     * Charset to be used by SimpleAR. Only used for database communication.
     *
     * @var string
     * @default 'utf8'
     */
    private $_charset = 'utf8';

    /**
     * Function used to "guess" model's table name.
     *
     * If table name is not set in model class. SimpleAR will use this function
     * to calculate one out of model class name.
     *
     * Example: if set to "strtoupper" and model class name is "Company", table
     * name will be set to "COMPANY".
     *
     * You can set this option to any function that accept a string as first
     * argument and that returns a string.
     *
     * @var string|function Default 'strtolower'
     */
    private $_classToTable = 'strtolower';

    /**
     * Should model attributes that are dates be automatically converted to
     * DateTime objects?
     *
     * If set to true, all date attributes of models will be converted
     * into a DateTime object. Actually, it is an extended version of the PHP
     * DateTime class that implement the __toString() magic method. When echoing
     * the attribute, it will automatically format the DateTime object with the
     * specified date format.
     *
     * @var bool
     * @see \SimpleAR\DateTime for the used DateTime class.
     * @see \SimpleAR\Config::$_dateFormat for documentation about how to set
     * date format.
     */
    private $_convertDateToObject = true;

    /**
     * Date format to be used by SimpleAR.
     * 
     * It will be used in conjunction with
     * DateTime attributes if Config::$_convertDateToObject is set to true.
     *
     * @var string A valid date format.
     *
     * Default value: 'Y-m-d'
     *
     * @see http://www.php.net/manual/en/datetime.formats.date.php for different
     * possible date formats.
     * @see \SimpleAR\Config::$_convertDateToObject to know more about date
     * attributes.
     */
    private $_dateFormat = 'Y-m-d';

    /**
     * Should we use debug mode?
     *
     * At the moment, only used by Database class to store database
     * queries and their execution time.
     *
     * @var bool
     *
     * Default value: false
     */
    private $_debug = false;

    /**
     * Should SimpleAR fire SQL queries to delete rows in cascade through tables?
     *
     * This is useful if used database is bad formatted (Lack of
     * foreign keys) or if database table engine does not support foreign keys.
     *
     * @var bool
     * Default value: false
     */
    private $_doForeignKeyWork = false;

    /**
     * DSN associative array.
     *
     * It *must* contain all these keys:
     *
     * - 'driver': PDO database driver ("mysql", "pgsql"...);
     * - 'host': Database host name;
     * - 'name': Database name;
     * - 'user': User to use to connect to database;
     * - 'password': User's password.
     *
     * You can also pass these *optional* keys:
     *
     * - 'charset': Database charset. Will use `Config::$_charset` if not given.
     *
     * @var array DSN associative array.
     * @throws Exception if one of required information is missing.
     * @see Config::$_charset
     */
    private $_dsn;

    /**
     * Suffix to append to calculated foreign key's base name for
     * constructing default foreign key name in model relationships.
     *
     * @var string
     *
     * Default value: 'Id'
     */
    private $_foreignKeySuffix = 'Id';

	/**
     * The suffix appended to model class's base name.
	 * For example, if model class suffix is "Model", model classes must be
	 * named like "UserModel", "CommentModel"...

	 * @var string
     *
     * Default value: ''
	 */
    private $_modelClassSuffix = '';

    /**
     * The path to the folder containing models.
     *
     * Example:
     * ```php
     * $oCfg->modelDirectory = 'my/model/directory/';
     * ```
     * Usually, you will only use one model directory source, but it may happen
     * that you need several ones. In that case, you can set a array containing
     * all the paths you need. It will check directories for model presence in
     * the order of apparition in the array.
     *
     * Example:
     * ```php
     * $oCfg->modelDirectory = array('my/model/directory/', 'my/other/path/');
     * ```
     *
     * @note Every path needs to end by a trailing slash.
     *
     * @var array
     *
     * Default value: './models/';
     */
    private $_modelDirectory = array('./models/');

    /**
     * Default primary key name.
     *
     * The default primary key name. If primary key is not specified
     * in the model class, this one will be used.
     *
     * @var string
     *
     * Default value: 'id';
     *
     * @see \SimpleAR\Model::$_mPrimaryKey for further information on primary
     * keys.
     */
    private $_primaryKey = 'id';

    /**
     * Allows developer to not write the underscore of attribute name.
     *
     * @param string $s The option name (same as attribute name without
     * beginning "_").
     *
     * @return mixed The option value.
     */
    public function __get($s)
    {
        if (isset($this->{'_' . $s}))
        {
            return $this->{'_' . $s};
        }

        $aTrace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $s .
            ' in ' . $aTrace[0]['file'] .
            ' on line ' . $aTrace[0]['line'],
            E_USER_NOTICE);

        return null;
    }

    /**
     * Allows developer to not write the underscore of attribute name.
     * Allows developer to write a special setter function for a specific
     * option.
     *
     * @param string $s The option name (same as attribute name without
     * beginning "_").
     * @param mixed $m The option value
     */
    public function __set($s, $m)
    {
        if (method_exists($this, $s))
        {
            $this->$s($m);
            return;
        }

        if (isset($this->{'_' . $s}))
        {
            $this->{'_' . $s} = $m;
            return;
        }

        $aTrace = debug_backtrace();
        trigger_error(
            'Undefined property via __set(): ' . $s .
            ' in ' . $aTrace[0]['file'] .
            ' on line ' . $aTrace[0]['line'],
            E_USER_NOTICE);

        return null;
    }

    /**
     * Setter for DSN ("dsn" option).
     *
     * This function checks that given array is complete.
     *
     * @param array $a The DSN array.
     * @see \SimpleAR\Config::$_dsn
     */
    public function dsn($a)
    {
        if (!isset($a['driver'], $a['host'], $a['name'], $a['user'], $a['password']))
        {
            throw new Exception('Database configuration array is not complete.');
        }

        $this->_dsn = array(
            'driver'   => $a['driver'],
            'host'     => $a['host'],
            'name'     => $a['name'],
            'user'     => $a['user'],
            'password' => $a['password'],
            'charset'  => isset($a['charset']) ? $a['charset'] : $this->_charset,
        );
    }

    /**
     * Model directory setter ("modelDirectory" option).
     *
     * This function checks that given path exists.
     *
     * @param string|array $m The directory path or an array containing
     * differents directory paths.
     * @see \SimpleAR\Config::$_modelDirectory
     */
    public function modelDirectory($m)
    {
        $a = (array) $m;

        foreach ($a as $s)
        {
            if (!is_dir($s))
            {
                throw new Exception('Model path "' . $s . '" does not exist.');
            }
        }

        $this->_modelDirectory = $a;
    }

}
