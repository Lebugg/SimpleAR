<?php
namespace SimpleAR;

/**
 * This file contains the ReadOnlyException class.
 *
 * @author Damien Launay
 */

/**
 * This exception is thrown by ReadOnlyModel.
 *
 * @package core
 */
class ReadOnlyException extends Exception
{
	public function __construct($sMethodName)
	{
		parent::__construct('You cannot use "' . $sMethodName . '" method because your model extends ReadOnlyModel.');
	}
}
