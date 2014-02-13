<?php
/**
 * This file contains the ReadOnlyException class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Exception;

use SimpleAR\Exception;

/**
 * This exception is thrown by ReadOnlyModel.
 */
class ReadOnly extends Exception
{
    /**
     * Constructor.
     *
     * @param string $methodName The method the user tried to use.
     */
	public function __construct($methodName)
	{
		parent::__construct('You cannot use "' . $methodName . '" method because your model extends ReadOnlyModel.');
	}
}
