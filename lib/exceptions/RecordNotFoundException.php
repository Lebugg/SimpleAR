<?php
/**
 * This file contains the RecordNotFoundException class.
 *
 * @author Lebugg
 */
namespace SimpleAR;

/**
 * Specific exception for when a specific record has not been found in database.
 */
class RecordNotFoundException extends Exception
{
    /**
     * Constructor.
     *
     * @param mixed $mId ID of Model instance that user tried to retrieve.
     */
	public function __construct($mId)
	{
        $sId = (is_string($mId) ? $mId : '(' .  implode(', ', $mId) . ')');
		parent::__construct('Record not found with ID: "' . $sId . '".');
	}
}
