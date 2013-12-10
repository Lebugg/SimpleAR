<?php
namespace SimpleAR;

require 'Condition.php';
require 'queries/Where.php';
require 'queries/Delete.php';
require 'queries/Insert.php';
require 'queries/Select.php';
require 'queries/Count.php';
require 'queries/Update.php';

abstract class Query
{
    protected static $_isCriticalQuery = false;

	public $sql;
	public $values = array();

    protected $_bUseModel  = true;
    protected $_bUseAlias  = true;

    /**
     * Attributes used when we use model classes.
     */
	protected $_sRootModel;
	protected $_oRootTable;

    /**
     * Attribute used when we use raw table name.
     */
	protected $_sRootTable = '';

	public function __construct($sRootModel)
	{
		if (class_exists($sRootModel))
		{
            $this->_sRootModel = $sRootModel;
            $this->_oRootTable = $sRootModel::table();
		}
		else
		{
			$this->_bUseModel  = false;
            $this->_bUseAlias  = false;
			$this->_sRootTable = $sRootModel;
		}
	}

	public abstract function build($aOptions);

	public static function count($aOptions, $oTable)
	{
		$oBuilder = new Query\Count($oTable);
        $oBuilder->build($aOptions);

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

    public function rowCount()
    {
        return $this->_oSth->rowCount();
    }

    public function run()
    {
        $oDb = Database::instance();

        if (static::$_isCriticalQuery)
        {
            if (! $this->_sWhere)
            {
                throw new Exception('Cannot execute this query without a WHERE clause.');
            }
        }

        $this->_oSth = $oDb->query($this->sql, $this->values);

        return $this;
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
}
