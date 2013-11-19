<?php
namespace SimpleAR;

require 'Condition.php';
require 'queries/Delete.php';
require 'queries/Insert.php';
require 'queries/Select.php';
require 'queries/Update.php';

abstract class Query
{
	public $sql;
	public $values = array();

	protected $_sRootModel;
	protected $_oRootTable;

	protected $_aConditions	= array();
	protected $_aAnds       = array();
    protected $_sAnds       = '';

	public function __construct($sRootModel)
	{
		$this->_sRootModel = $sRootModel;
		$this->_oRootTable = $sRootModel::table();
	}

	public abstract function build($aOptions);

	public static function count($aOptions, $oTable)
	{
		$oBuilder = new Query\Select($oTable);
        $oBuilder->buildCount($aOptions);

		return $oBuilder;
	}

	public static function delete($aConditions, $oTable)
	{
		$oBuilder = new Query\Delete($oTable);
        $oBuilder->build($aConditions);

		return $oBuilder;
	}

	public static function insert($aOptions, $oTable)
	{
		$oBuilder = new Query\Insert($oTable);
        $oBuilder->build($aOptions);

		return $oBuilder;
	}

    public function run()
    {
        $oDb = Database::instance();

        return $oDb->query($this->sql, $this->values);
    }

	public static function select($aOptions, $oTable)
	{
		$oBuilder = new Query\Select($oTable);
        $oBuilder->build($aOptions);

		return $oBuilder;
	}

	public static function update($aOptions, $oTable)
	{
		$oBuilder = new Query\Update($oTable);
        $oBuilder->build($aOptions);

		return $oBuilder;
	}

	protected function _where()
	{
		return ($this->_sAnds) ? ' WHERE ' . $this->_sAnds : '';
	}
}
