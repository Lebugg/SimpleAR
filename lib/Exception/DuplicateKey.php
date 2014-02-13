<?php
/**
 * This file contains the DuplicateKeyException class.
 *
 * @author Lebugg
 */
namespace SimpleAR\Exception;

use SimpleAR\Exception;

/**
 * Specific exception for database unique constraint failure.
 *
 * @package core
 */
class DuplicateKey extends Exception {}
