<?php
namespace SimpleAR;

/**
 * This file contains the ReadOnlyException class.
 *
 * @author Lebugg
 */

/**
 * This exception is thrown by ReadOnlyModel.
 */
class ReadOnlyException extends Exception
{
    /**
     * Constructor.
     *
     * @param string $sMethodName The method the user tried to use.
     */
	public function __construct($sMethodName)
	{
		parent::__construct('You cannot use "' . $sMethodName . '" method because your model extends ReadOnlyModel.');
	}
}
