<?php
namespace SimpleAR;

require 'Query.php';

class Sql
{
	public static function delete($aOptions, $oTable)
	{
		$oBuilder = new Query\Delete($oTable);
		return $oBuilder->build($aOptions);
	}

	public static function select($aOptions, $oTable)
	{
		$oBuilder = new Query\Select($oTable);
		return $oBuilder->build($aOptions);
	}

	public static function count($aOptions, $oTable)
	{
		$oBuilder = new Query\Select($oTable);
		return $oBuilder->buildCount($aOptions);
	}
}
