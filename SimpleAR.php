<?php
/**
 * This file is the entry point of the library.
 *
 * It does the following:
 *
 * 1. Checks PHP version.
 * 2. Defines library version.
 * 3. Includes all library files.
 * 4. Defines the `init()` function.
 *
 * To initialize the library, just call `init()` passing your Config object as parameter:
 *
 *  ```php
 *  include 'libraries/SimpleAR/SimpleAR.php';
 *  
 *  $oCfg = SimpleAR\Config::instance();
 *  $oCfg->dsn = array(
 *      'driver'   => DB_DRIVER,
 *      'host'     => DB_HOST,
 *      'name'     => DB_NAME,
 *      'user'     => DB_USER,
 *      'password' => DB_PASS,
 *  );
 *  
 *  // Note trailing slash.
 *  $oCfg->modelDirectory  = 'path/to/any/directory_you_want/';
 *  
 *  SimpleAR\init($oCfg);
 *  ```
 *
 * @author Lebugg
 */
namespace SimpleAR;

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
{
	die('SimpleAR requires PHP 5.3 or higher.');
}

define('PHP_SIMPLEAR_VERSION_ID', '1.0');

require __DIR__ . '/lib/Config.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Table.php';
require __DIR__ . '/lib/Query.php';
require __DIR__ . '/lib/Model.php';
require __DIR__ . '/lib/ReadOnlyModel.php';
require __DIR__ . '/lib/Relationship.php';
require __DIR__ . '/lib/exceptions/Exception.php';
require __DIR__ . '/lib/exceptions/DatabaseException.php';
require __DIR__ . '/lib/exceptions/DuplicateKeyException.php';
require __DIR__ . '/lib/exceptions/RecordNotFoundException.php';
require __DIR__ . '/lib/exceptions/ReadOnlyException.php';
require __DIR__ . '/lib/exceptions/MalformedOptionException.php';

require __DIR__ . '/tools/array_merge_recursive_distinct.php';

/**
 * This function initializes the library.
 *
 * @param Config $oConfig Your configuration object.
 *
 * @see Config
 */
function init($oConfig)
{
    if ($oConfig->convertDateToObject)
    {
        require 'lib/DateTime.php';

        DateTime::setFormat($oConfig->dateFormat);
    }

    spl_autoload_register(function($sClass) use ($oConfig) {
        foreach ($oConfig->modelDirectory as $sPath)
        {
            if (file_exists($sFile = $sPath . $sClass . '.php'))
            {
                include $sFile;

                /**
                 * Loaded class might not be a subclass of Model. It can just be a
                 * independant model class located in same directory and loaded by this
                 * autoload function.
                 */
                if (is_subclass_of($sClass, 'SimpleAR\Model'))
                {
                    $sClass::wakeup();
                }

                // We have included our model, stop here.
                break;
            }
        }
    });

    $oDatabase = new Database($oConfig);

    Model::init($oConfig, $oDatabase);
    Relationship::init($oConfig, $oDatabase);
    Query::init($oDatabase);

    return $oDatabase;
}
