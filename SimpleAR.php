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
require __DIR__ . '/lib/Relation.php';
require __DIR__ . '/lib/Exception.php';
require __DIR__ . '/lib/DateTime.php';
require __DIR__ . '/lib/Facades/Facade.php';
require __DIR__ . '/lib/Facades/DB.php';
require __DIR__ . '/lib/Facades/Cfg.php';
require __DIR__ . '/tools/array_merge_recursive_distinct.php';

use \SimpleAR\Config;
use \SimpleAR\Database;
use \SimpleAR\DateTime;
use \SimpleAR\Facades\Facade;

class SimpleAR
{
    public $cfg;
    public $db;

    /**
     * This function initializes the library.
     *
     * @param Config $config Your configuration object.
     *
     * @see Config
     */
    public function __construct(Config $config)
    {
        $this->cfg = $config;
        $this->db  = new Database($config);

        // Dependency injection is just this. I tried to use the Facade pattern
        // the way of Laravel framework uses this; but this is a very
        // lightweight and not-as-clean implementation.
        // Facades are located in lib/Facades/.
        Facade::bind($this);

        DateTime::setFormat($config->dateFormat);

        spl_autoload_register(function($class) use ($config) {
            foreach ($config->modelDirectory as $path)
            {
                if (file_exists($file = $path . $class . '.php'))
                {
                    include $file;

                    // We check to things:
                    //
                    //  1) Class is a subclass of SimpleAR's Model base class:
                    //  it might be an independant class located in the model
                    //  folder.
                    //  2) Class is not abstract: wake up has no sense on
                    //  abstract class.
                    $reflection = new ReflectionClass($class);
                    if ($reflection->isSubclassOf('SimpleAR\Model')
                        && ! $reflection->isAbstract())
                    {
                        $class::wakeup();
                    }

                    // We have included our model, stop here.
                    break;
                }
            }
        });
    }
}
