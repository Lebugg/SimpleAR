<?php
namespace SimpleAR;

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300)
{
	die('SimpleAR requires PHP 5.3 or higher.');
}

define('PHP_SIMPLEAR_VERSION_ID', '1.0');

require 'lib/Config.php';
require 'lib/Database.php';
require 'lib/Table.php';
require 'lib/Query.php';
require 'lib/Model.php';
require 'lib/ReadOnlyModel.php';
require 'lib/Relationship.php';
require 'lib/exceptions/Exception.php';
require 'lib/exceptions/DatabaseException.php';
require 'lib/exceptions/DuplicateKeyException.php';
require 'lib/exceptions/RecordNotFoundException.php';
require 'lib/exceptions/ReadOnlyException.php';
require 'lib/Tools.php';

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
                    $sClass::init();
                }

                // We have included our model, stop here.
                break;
            }
        }
    });
}
