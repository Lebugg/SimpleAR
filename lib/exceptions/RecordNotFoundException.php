<?php
namespace SimpleAR;

/**
 * This file contains the ApiResourceNotFoundException class.
 *
 * @author Damien Launay
 */

/**
 * This class extends ApiException class.
 *
 * @package core
 */
class RecordNotFoundException extends Exception
{
	public function __construct($mPK)
	{
		parent::__construct('Record not found with ID: "' . $mPK . '".');
	}
}
