<?php
/**
 * This file contains the DatabaseException class.
 *
 * @author Lebugg
 */
namespace SimpleAR;

/**
 * This class handles exception coming from database.
 *
 * Example:
 *  ```php
 *  try {
 *      /// Stuff with PDO (i.e. execute a query).
 *  }
 *  catch (\PDOException $oEx)
 *  {
 *      throw new DatabaseException($oEx->getMessage(), $sQuery, $oEx);
 *  }
 *  ```
 */
class DatabaseException extends Exception
{
    /**
     * Constructor.
     *
     * Parameters meaning is a bit different than classic Exception class.
     *
     * @param string $sMessage See \Exception::__construct().
     * @param string $sQuery   The SQL query that caused error. It will be concat to exception
     * message.
     * @param string $oPrevious See \Exception::__construct().
     */
    public function __construct($sMessage, $sQuery, \Exception $oPrevious = null)
	{
		$s  = 'A database error occured' . PHP_EOL;
		$s .= 'Error: ' . $sMessage . PHP_EOL;

		if ($sQuery)
		{
			$s .= 'SQL query: ' . $sQuery . PHP_EOL;
		}

        parent::__construct($s, 0, $oPrevious);
	}
}
