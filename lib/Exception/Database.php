<?php
/**
 * This file contains the DatabaseException class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Exception;

use SimpleAR\Exception;

/**
 * This class handles exception coming from database.
 *
 * Example:
 *  ```php
 *  try {
 *      /// Stuff with PDO (i.e. execute a query).
 *  }
 *  catch (\PDOException $ex)
 *  {
 *      throw new Database($ex->getMessage(), $query, $ex);
 *  }
 *  ```
 */
class Database extends Exception
{
    /**
     * Constructor.
     *
     * Parameters meaning is a bit different than classic Exception class.
     *
     * @param string $message See \Exception::__construct().
     * @param string $query   The SQL query that caused error. It will be concat to exception
     * message.
     * @param string $previous See \Exception::__construct().
     */
    public function __construct($message, $query, \Exception $previous = null)
	{
		$s  = 'A database error occured' . PHP_EOL;
		$s .= 'Error: ' . $message . PHP_EOL;

		if ($query)
		{
			$s .= 'SQL query: ' . $query . PHP_EOL;
		}

        parent::__construct($s, 0, $previous);
	}
}
