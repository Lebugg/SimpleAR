<?php
namespace SimpleAR;

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
{
	die('SimpleAR requires PHP 5.3 or higher.');
}

define('PHP_SIMPLEAR_VERSION_ID', '1.0');

require 'Config.php';
require 'Database.php';
require 'Table.php';
require 'Query.php';
require 'Model.php';
require 'ReadOnlyModel.php';
require 'Relationship.php';
require 'exceptions/Exception.php';
require 'exceptions/DatabaseException.php';
require 'exceptions/DuplicateKeyException.php';
require 'exceptions/RecordNotFoundException.php';
require 'exceptions/ReadOnlyException.php';
require 'Tools.php';

function init()
{
    $oConfig = Config::instance();

    if ($oConfig->convertDateToObject)
    {
        require 'DateTime.php';

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
                    $sClass::init();
                }

                // We have included our model, stop here.
                break;
            }
        }
    });
}
