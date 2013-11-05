<?php
namespace SimpleAR;

class DatabaseException extends Exception
{
	public static function construct($sMessage, $sQuery = null)
	{
		$s  = 'A database error occured' . "\n";
		$s .= 'Error: ' . $sMessage . "\n";

		if ($sQuery)
		{
			$s .= 'SQL query: ' . $sQuery . "\n";
		}

		return (new DatabaseException($s));
	}
}
