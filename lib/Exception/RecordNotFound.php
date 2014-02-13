<?php
/**
 * This file contains the RecordNotFoundException class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Exception;

use SimpleAR\Exception;

/**
 * Specific exception for when a specific record has not been found in database.
 */
class RecordNotFound extends Exception
{
    /**
     * Constructor.
     *
     * @param mixed $id ID of Model instance that user tried to retrieve.
     */
	public function __construct($id)
	{
        $id = is_array($id) ? '(' .  implode(', ', $id) . ')' : $id;
		parent::__construct('Record not found with ID: "' . $id . '".');
	}
}
