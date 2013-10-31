<?php
namespace SimpleAR;

class Table
{
    private $_sName;
    private $_mPrimaryKey;
    private $_aColumns;

	private $_sAlias;
    private $_bIsSimplePrimaryKey;

    public function __construct($sName, $mPrimaryKey, $aColumns)
    {
        $this->_sName       = $sName;
        $this->_mPrimaryKey = $mPrimaryKey;
        $this->_aColumns    = $aColumns;

		$this->_sAlias				= '_' . strtolower($sName);
        $this->_bIsSimplePrimaryKey = is_string($mPrimaryKey);
    }

	public function alias()
	{
		return $this->_sAlias;
	}

    public function columns()
    {
        return $this->_aColumns;
    }

    /**
     * Gives a DB field name according to its key aka a class member name.
     *
     * @param string|array $mKey The key of $_aColumns. If $mKey is equal to "id", it
     * will return the model primary key.
     *
     * @return string|array The DB field name.
     */
    public function columnRealName($mKey)
    {
        if ($mKey === 'id') { return $this->_mPrimaryKey; }

        return is_string($mKey)
            ? $this->_aColumns[$mKey]
            : array_intersect_key($this->_aColumns, array_flip($mKey))
            ;

    }

    public function hasColumn($s)
    {
        return $s == 'id' || isset($this->_aColumns[$s]);
    }

    public function name()
    {
        return $this->_sName;
    }

    public function primaryKey()
    {
        return $this->_mPrimaryKey;
    }

    public function isSimplePrimaryKey()
    {
        return $this->_bIsSimplePrimaryKey;
    }
}
