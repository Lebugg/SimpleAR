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

define('PHP_SIMPLEAR_VERSION_ID', '1.2.15');

require __DIR__ . '/Config.php';
require __DIR__ . '/Orm/Table.php';
require __DIR__ . '/Orm/Model.php';
require __DIR__ . '/Orm/Builder.php';
require __DIR__ . '/Orm/Relation.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Database/Query.php';
require __DIR__ . '/Exception.php';
require __DIR__ . '/DateTime.php';
require __DIR__ . '/Facades/Facade.php';
require __DIR__ . '/Facades/DB.php';
require __DIR__ . '/Facades/Cfg.php';
require __DIR__ . '/../tools/array_merge_recursive_distinct.php';
require __DIR__ . '/../tools/is_valid_model_class.php';
require __DIR__ . '/../tools/decamelize.php';
require __DIR__ . '/../tools/flatten_array.php';

use \SimpleAR\Config;
use \SimpleAR\Database;
use \SimpleAR\Database\Connection;
use \SimpleAR\DateTime;
use \SimpleAR\Facades\Facade;
use \SimpleAR\Orm\Model;

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
    public function __construct(Config $cfg)
    {
        $this->initialize($cfg);
    }

    public function initialize(Config $cfg)
    {
        $this->configure($cfg);
        $this->connect(new Connection($cfg));
        $this->coat($this);
        $this->registerAutoload($cfg->modelDirectory);
    }

    public function configure(Config $cfg)
    {
        $this->cfg = $cfg;
        $this->cfg->apply();
        $this->localize($cfg->dateFormat);
    }

    public function connect(Connection $conn)
    {
        $this->db = new Database($conn);
    }

    public function coat(SimpleAR $sar)
    {
        // Dependency injection is just this. I tried to use the Facade pattern
        // the way of Laravel framework uses this; but this is a very
        // lightweight and not-as-clean implementation.
        // Facades are located in lib/Facades/.
        Facade::bind($sar);

        // Boot model class. It will load its dependency.
        Model::boot();
    }

    public function localize($df)
    {
        DateTime::setFormat($df);
    }

    public function registerAutoload(array $directories)
    {
        spl_autoload_register(function($class) use ($directories) {
            foreach ($directories as $path)
            {
                if (file_exists($file = $path . $class . '.php'))
                {
                    include $file;

                    // We check two things:
                    //
                    //  1) Class is a subclass of SimpleAR's Model base class:
                    //  it might be an independant class located in the model
                    //  folder.
                    //  2) Class is not abstract: wake up has no sense on
                    //  abstract class.
                    if (is_valid_model_class($class))
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
