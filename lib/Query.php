<?php
namespace SimpleAR;

require 'Condition.php';
require 'query/Delete.php';
require 'query/Select.php';

abstract class Query
{
	protected $_sRootModel;
	protected $_oRootTable;

	protected $_sQuery;
	protected $_aValues = array();

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
        $oBuilder->build($aConditions);

		return $oBuilder;
	}

    public function run()
    {
        static $oDb = Database::instance();

        return $oDb->query($this->_sQuery, $this->_aValues);
    }

	public static function select($aOptions, $oTable)
	{
		$oBuilder = new Query\Select($oTable);
        $oBuilder->build($aConditions);

		return $oBuilder;
	}

	public static function update($aOptions, $oTable)
	{
		$oBuilder = new Query\Update($oTable);
        $oBuilder->build($aConditions);

		return $oBuilder;
	}

	protected function _where()
	{
		return ($this->_sAnds) ? ' WHERE ' . $this->_sAnds : '';
	}
}
