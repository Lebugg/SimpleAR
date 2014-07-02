<?php namespace SimpleAR;
/**
 * This file contains the Exception class.
 *
 * @author Lebugg
 */

require __DIR__ . '/Exception/Database.php';
require __DIR__ . '/Exception/DuplicateKey.php';
require __DIR__ . '/Exception/RecordNotFound.php';
require __DIR__ . '/Exception/ReadOnly.php';
require __DIR__ . '/Exception/MalformedOptionException.php';

/**
 * Main SimpleAR exception class.
 */
class Exception extends \Exception {}
