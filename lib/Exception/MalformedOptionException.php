<?php namespace SimpleAR\Exception;
/**
 * This file contains the MalformedOptionException class.
 *
 * @author Lebugg
 */

use SimpleAR\Exception;

/**
 * This exception is thrown by query classes.
 */
class MalformedOptionException extends Exception
{
    public function __construct($optionName, $optionString, \Exception $previous = null)
	{
        $error = 'Option "' . $optionName . '" is not well-formed:' . "\n"
            . 'Given option: ' . $optionString;

        parent::__construct($error, 0, $previous);
	}
}
