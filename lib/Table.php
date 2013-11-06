<?php
namespace SimpleAR;

class Table
{
    public $name;
    public $primaryKey;
    public $columns;
    public $orderBy;

	// Constructed.
	public $alias;
    public $isSimplePrimaryKey;

    public function __construct($sName, $mPrimaryKey, $aColumns, $aOrderBy)
    {
        $this->name       = $sName;
        $this->primaryKey = $mPrimaryKey;
        $this->columns    = $aColumns;
        $this->orderBy    = $aOrderBy;

		$this->alias		      = '_' . strtolower($sName);
        $this->isSimplePrimaryKey = is_string($mPrimaryKey);
    }

    /**
     * Gives a column name according to its key that is a class member name.
     *
     * @param string|array $mKey The key of $_aColumns. If $mKey is equal to "id", it
     * will return the model primary key.
     *
     * @return string|array The DB field name.
     */
    public function columnRealName($mKey)
    {
        if ($mKey === 'id') { return $this->primaryKey; }

        return is_string($mKey)
            ? $this->columns[$mKey]
            : array_intersect_key($this->columns, array_flip($mKey))
            ;

    }

    public function hasColumn($s)
    {
        return $s == 'id' || isset($this->columns[$s]);
    }
}
