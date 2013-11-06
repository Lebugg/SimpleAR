<?php
namespace SimpleAR;

require 'Query.php';

class Sql
{
	public static function delete($aOptions, $oTable)
	{
		require 'query/Delete.php';

		$oBuilder = new Query\Delete($oTable);
		return $oBuilder->build($aOptions);
	}

	public static function select($aOptions, $oTable)
	{
		require 'query/Select.php';

		$oBuilder = new Query\Select($oTable);
		return $oBuilder->build($aOptions);
	}

}
