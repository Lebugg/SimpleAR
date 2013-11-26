<?php
namespace SimpleAR;

class Config
{
    private $_dsn;
   
    private $_autoRetrieveModelColumns = false;
    private $_charset                  = 'utf8';
    private $_classToTable             = 'strtolower';
    private $_convertDateToObject      = true;
    private $_dateFormat               = 'Y-m-d';
    private $_debug                    = true;
    private $_doForeignKeyWork         = false;
    private $_foreignKeySuffix         = 'Id';
	private $_locale				   = 'en_US';

	/**
	 * The suffix appended to model class's base name.
	 * For example, if model class suffix is "Model", model classes must be
	 * named like "UserModel", "CommentModel"...
	 */
    private $_modelClassSuffix         = '';
    private $_modelDirectory           = './models/';
    private $_primaryKey               = 'id';

    // Singleton instance.
    private static $_o = null;

    private function __construct() {}
    private function __clone()     {}

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

    public static function instance()
    {
        if (self::$_o === null)
        {
            self::$_o = new Config();
        }

        return self::$_o;
    }

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

    public function modelDirectory($s)
    {
        if (!is_dir($s))
        {
            throw new Exception('Model path "' . $s . '" does not exist.');
        }

        $this->_modelDirectory = $s;
    }

}
